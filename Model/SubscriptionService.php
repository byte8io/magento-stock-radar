<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Validator\EmailAddress;

/**
 * Single entry point for subscribing/unsubscribing. Used by both the storefront
 * controller and the GraphQL resolvers so validation and side-effects stay in
 * one place.
 */
class SubscriptionService
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly EmailHasher $emailHasher,
        private readonly UnsubscribeTokenGenerator $tokenGenerator,
        private readonly EmailAddress $emailValidator,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Subscribe an email to a product. Idempotent — repeat calls return the
     * existing subscription rather than failing.
     *
     * @return array{created: bool}
     */
    public function subscribe(
        string $sku,
        string $email,
        int $storeId,
        ?int $customerId = null
    ): array {
        $email = trim($email);
        if (!$this->emailValidator->isValid($email)) {
            throw new LocalizedException(__('Please provide a valid email address.'));
        }

        if (!$this->config->isActive($storeId)) {
            throw new LocalizedException(__('Stock notifications are not enabled for this store.'));
        }

        $product = $this->productRepository->get($sku, false, $storeId);
        $productId = (int) $product->getId();

        // Only allow subscribing to currently OOS products — otherwise there's
        // nothing to wait for. Configurable parents may show in-stock while
        // their child is OOS, so when we get a configurable here we let it
        // through; the per-variant case is handled by the caller passing the
        // simple SKU directly.
        if ($product->getTypeId() === 'simple') {
            $stockItem = $this->stockRegistry->getStockItem($productId);
            if ($stockItem->getIsInStock() && $stockItem->getQty() > 0) {
                throw new LocalizedException(__('This product is currently in stock.'));
            }
        }

        $parentProductId = $product->getTypeId() === 'simple'
            ? $this->resolveParentProductId($productId)
            : null;

        $created = $this->subscriptionResource->upsertPending(
            $productId,
            $parentProductId,
            $storeId,
            $customerId,
            $email,
            $this->emailHasher->hash($email),
            $this->tokenGenerator->generate()
        );

        return ['created' => $created];
    }

    /**
     * Unsubscribe by signed token. Returns true if a row was actually flipped,
     * false if the token didn't match (we don't reveal which to the caller —
     * surface the same success message either way).
     */
    public function unsubscribeByToken(string $token): bool
    {
        if ($token === '') {
            return false;
        }

        $connection = $this->subscriptionResource->getConnection();
        $rows = (int) $connection->update(
            $this->subscriptionResource->getMainTable(),
            [SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_CANCELLED],
            [
                SubscriptionInterface::UNSUBSCRIBE_TOKEN . ' = ?' => $token,
                SubscriptionInterface::STATUS . ' = ?' => SubscriptionInterface::STATUS_PENDING,
            ]
        );

        return $rows > 0;
    }

    /**
     * Returns null if the simple has no parent configurable. Uses
     * configurable_product_link table directly to avoid loading parent models.
     */
    private function resolveParentProductId(int $simpleProductId): ?int
    {
        $connection = $this->subscriptionResource->getConnection();
        $select = $connection->select()
            ->from(
                $connection->getTableName('catalog_product_super_link'),
                ['parent_id']
            )
            ->where('product_id = ?', $simpleProductId)
            ->limit(1);

        $parentId = $connection->fetchOne($select);
        return $parentId ? (int) $parentId : null;
    }
}
