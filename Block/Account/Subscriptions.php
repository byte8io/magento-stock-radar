<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Block\Account;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\View\Element\Template;
use Magento\Store\Model\StoreManagerInterface;

class Subscriptions extends Template
{
    /** @var array<int, array<string, mixed>>|null */
    private ?array $itemsCache = null;

    public function __construct(
        Template\Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManagerInternal,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     sku: string,
     *     product_name: string,
     *     product_url: ?string,
     *     status: string,
     *     created_at: string,
     *     notified_at: ?string,
     *     unsubscribe_token: string
     * }>
     */
    public function getSubscriptions(): array
    {
        if ($this->itemsCache !== null) {
            return $this->itemsCache;
        }

        if (!$this->customerSession->isLoggedIn()) {
            return $this->itemsCache = [];
        }

        $customerId = (int) $this->customerSession->getCustomerId();
        $storeId = (int) $this->storeManagerInternal->getStore()->getId();

        $collection = $this->collectionFactory->create()
            ->addFieldToFilter(SubscriptionInterface::CUSTOMER_ID, $customerId)
            ->addFieldToFilter(SubscriptionInterface::STORE_ID, $storeId)
            ->addFieldToFilter(SubscriptionInterface::STATUS, [
                'in' => [SubscriptionInterface::STATUS_PENDING, SubscriptionInterface::STATUS_NOTIFIED],
            ])
            ->setOrder(SubscriptionInterface::CREATED_AT, 'DESC');

        $items = [];
        foreach ($collection as $subscription) {
            $productId = (int) $subscription->getData(SubscriptionInterface::PRODUCT_ID);
            try {
                $product = $this->productRepository->getById($productId, false, $storeId);
                $sku = (string) $product->getSku();
                $name = (string) $product->getName();
                $url = (string) $product->getProductUrl();
            } catch (NoSuchEntityException $e) {
                $sku = '';
                $name = (string) __('Product no longer available');
                $url = null;
            }

            $items[] = [
                'id' => (int) $subscription->getData(SubscriptionInterface::ENTITY_ID),
                'sku' => $sku,
                'product_name' => $name,
                'product_url' => $url,
                'status' => (string) $subscription->getData(SubscriptionInterface::STATUS),
                'created_at' => (string) $subscription->getData(SubscriptionInterface::CREATED_AT),
                'notified_at' => $subscription->getData(SubscriptionInterface::NOTIFIED_AT),
                'unsubscribe_token' => (string) $subscription->getData(SubscriptionInterface::UNSUBSCRIBE_TOKEN),
            ];
        }

        return $this->itemsCache = $items;
    }

    public function getCancelUrl(string $token): string
    {
        return $this->getUrl('stockradar/subscription/unsubscribe', ['token' => $token]);
    }

    public function getStatusLabel(string $status): \Magento\Framework\Phrase
    {
        return match ($status) {
            SubscriptionInterface::STATUS_PENDING => __('Waiting for restock'),
            SubscriptionInterface::STATUS_NOTIFIED => __('Notified'),
            SubscriptionInterface::STATUS_CANCELLED => __('Cancelled'),
            SubscriptionInterface::STATUS_BOUNCED => __('Email bounced'),
            default => __($status),
        };
    }
}
