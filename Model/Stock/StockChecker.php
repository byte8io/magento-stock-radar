<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\Stock;

use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * "Is this product in stock for the given store's website?" — the only
 * stock check Stock Radar cares about.
 *
 * MSI-aware with legacy CatalogInventory fallback. Pattern mirrors
 * Byte8\Preorder\Model\Inventory\StockResolver so both modules behave
 * identically on MSI / non-MSI stores. MSI interfaces are resolved
 * lazily via ObjectManager so this class still loads on stores where
 * the MSI modules are disabled — falling back to the legacy
 * StockRegistry::getStockItemBySku() check.
 *
 * The legacy StockRegistry is not reliable on MSI stores: the
 * `cataloginventory_stock_item` table is not kept in sync with
 * source-item quantities, so `is_in_stock` and `qty` can both lag
 * behind the real MSI state. MSI's IsProductSalable is the
 * authoritative check (accounts for source items + reservations +
 * stock status flag).
 */
class StockChecker
{
    private ?bool $msiAvailable = null;

    public function __construct(
        private readonly StockRegistryInterface $stockRegistry,
        private readonly ModuleManager $moduleManager,
        private readonly ObjectManagerInterface $objectManager,
        private readonly StoreManagerInterface $storeManager
    ) {
    }

    /**
     * True when the product is salable for the website that owns the
     * given store. Unknown SKUs and errors are conservatively treated
     * as in-stock so we don't render a notify-me form (or reject a
     * subscribe) on what's really a misconfiguration.
     */
    public function isInStock(string $sku, ?int $storeId = null): bool
    {
        if ($sku === '') {
            return true;
        }

        if ($this->isMsiAvailable()) {
            return $this->isSalableMsi($sku, $storeId);
        }

        return $this->isInStockLegacy($sku);
    }

    public function isMsiAvailable(): bool
    {
        if ($this->msiAvailable === null) {
            $this->msiAvailable = $this->moduleManager->isEnabled('Magento_InventorySalesApi')
                && $this->moduleManager->isEnabled('Magento_InventoryCatalogApi');
        }

        return $this->msiAvailable;
    }

    public function getStockId(?int $storeId = null): int
    {
        if (!$this->isMsiAvailable()) {
            return 1;
        }

        try {
            $websiteCode = (string) $this->storeManager->getStore($storeId)->getWebsite()->getCode();

            /** @var \Magento\InventorySalesApi\Api\StockResolverInterface $stockResolver */
            $stockResolver = $this->objectManager->get(
                \Magento\InventorySalesApi\Api\StockResolverInterface::class
            );

            return (int) $stockResolver->execute('website', $websiteCode)->getStockId();
        } catch (\Throwable) {
            return 1;
        }
    }

    private function isSalableMsi(string $sku, ?int $storeId): bool
    {
        try {
            $stockId = $this->getStockId($storeId);

            /** @var \Magento\InventorySalesApi\Api\IsProductSalableInterface $isProductSalable */
            $isProductSalable = $this->objectManager->get(
                \Magento\InventorySalesApi\Api\IsProductSalableInterface::class
            );

            return $isProductSalable->execute($sku, $stockId);
        } catch (\Throwable) {
            return $this->isInStockLegacy($sku);
        }
    }

    private function isInStockLegacy(string $sku): bool
    {
        try {
            $stockItem = $this->stockRegistry->getStockItemBySku($sku);
            return (bool) $stockItem->getIsInStock();
        } catch (\Throwable) {
            return true;
        }
    }
}
