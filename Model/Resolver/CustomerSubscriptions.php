<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\Resolver;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription\CollectionFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\GraphQl\Config\Element\Field;
use Magento\Framework\GraphQl\Exception\GraphQlAuthorizationException;
use Magento\Framework\GraphQl\Query\ResolverInterface;
use Magento\Framework\GraphQl\Schema\Type\ResolveInfo;
use Magento\GraphQl\Model\Query\ContextInterface;

class CustomerSubscriptions implements ResolverInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function resolve(
        Field $field,
        $context,
        ResolveInfo $info,
        ?array $value = null,
        ?array $args = null
    ) {
        /** @var ContextInterface $context */
        if (!$context->getExtensionAttributes()->getIsCustomer()) {
            throw new GraphQlAuthorizationException(__('Customer authentication required.'));
        }

        $customerId = (int) $context->getUserId();
        $storeId = (int) $context->getExtensionAttributes()->getStore()->getId();

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
            $productName = null;
            $productUrl = null;
            try {
                $product = $this->productRepository->getById($productId, false, $storeId);
                $productName = (string) $product->getName();
                $productUrl = (string) $product->getProductUrl();
                $sku = (string) $product->getSku();
            } catch (NoSuchEntityException $e) {
                $sku = '';
            }

            $items[] = [
                'sku' => $sku,
                'product_name' => $productName,
                'product_url' => $productUrl,
                'status' => (string) $subscription->getData(SubscriptionInterface::STATUS),
                'created_at' => (string) $subscription->getData(SubscriptionInterface::CREATED_AT),
                'notified_at' => $subscription->getData(SubscriptionInterface::NOTIFIED_AT),
                'unsubscribe_token' => (string) $subscription->getData(SubscriptionInterface::UNSUBSCRIBE_TOKEN),
            ];
        }

        return [
            'items' => $items,
            'total_count' => count($items),
        ];
    }
}
