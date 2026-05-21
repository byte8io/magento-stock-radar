<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Observer;

use Byte8\StockRadar\Model\Activation\Activation;
use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Magento\CatalogInventory\Api\Data\StockItemInterface;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Listens on cataloginventory_stock_item_save_after. When is_in_stock flips from
 * 0 to 1 (or qty rises from 0 to >0), enqueue a dispatch row per pending
 * subscription, each with a randomly-staggered scheduled_at within the configured
 * throttle window.
 *
 * The throttle is the whole point: we never blast all subscribers simultaneously.
 */
class StockSaveObserver implements ObserverInterface
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly DispatchResource $dispatchResource,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger,
        private readonly Activation $activation
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var StockItemInterface|null $item */
        $item = $observer->getEvent()->getItem();
        if (!$item instanceof StockItemInterface) {
            return;
        }

        if (!$this->cameBackInStock($item)) {
            return;
        }

        $productId = (int) $item->getProductId();
        if ($productId <= 0) {
            return;
        }

        try {
            foreach ($this->storeManager->getStores() as $store) {
                $storeId = (int) $store->getId();
                if (!$this->config->isActive($storeId)) {
                    continue;
                }

                if (!$this->activation->isActive($storeId)) {
                    continue;
                }

                $pending = $this->subscriptionResource->getPendingIdsForProduct($productId, $storeId);
                if ($pending === []) {
                    continue;
                }

                $window = $this->config->getThrottleWindowMinutes($storeId);
                $this->dispatchResource->enqueueBatch($pending, $window);
            }
        } catch (\Throwable $e) {
            // Never let a stock-save fail because Stock Radar enqueueing did
            $this->logger->error('Byte8 StockRadar enqueue failed: ' . $e->getMessage(), ['exception' => $e]);
        }
    }

    private function cameBackInStock(StockItemInterface $item): bool
    {
        // is_in_stock 0 → 1 OR qty 0 → >0 with stock_status enabled
        $origIsInStock = (int) $item->getOrigData(StockItemInterface::IS_IN_STOCK);
        $newIsInStock = (int) $item->getIsInStock();
        if ($origIsInStock === 0 && $newIsInStock === 1) {
            return true;
        }

        $origQty = (float) $item->getOrigData(StockItemInterface::QTY);
        $newQty = (float) $item->getQty();
        if ($origQty <= 0 && $newQty > 0 && $newIsInStock === 1) {
            return true;
        }

        return false;
    }
}
