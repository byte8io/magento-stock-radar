<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Block\Product\View;

use Byte8\StockRadar\Model\ConfigInterface;
use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogInventory\Api\StockRegistryInterface;
use Magento\ConfigurableProduct\Model\Product\Type\Configurable as ConfigurableType;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Registry;
use Magento\Framework\Serialize\SerializerInterface;
use Magento\Framework\View\Element\Template;

class NotifyMe extends Template
{
    public function __construct(
        Template\Context $context,
        private readonly Registry $registry,
        private readonly ConfigInterface $config,
        private readonly StockRegistryInterface $stockRegistry,
        private readonly CustomerSession $customerSession,
        private readonly SerializerInterface $serializer,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * Variant ID → SKU map for configurable products. Returned as a JSON
     * string so the storefront JS can resolve the simple SKU when the user
     * selects swatch options.
     *
     * Returns "{}" when the current product isn't configurable, so the JS
     * doesn't have to special-case it.
     */
    public function getVariantSkuMapJson(): string
    {
        $product = $this->getProduct();
        if (!$product || $product->getTypeId() !== ConfigurableType::TYPE_CODE) {
            return '{}';
        }

        $map = [];
        $typeInstance = $product->getTypeInstance();
        if ($typeInstance instanceof ConfigurableType) {
            foreach ($typeInstance->getUsedProducts($product) as $variant) {
                $map[(int) $variant->getId()] = (string) $variant->getSku();
            }
        }

        return $this->serializer->serialize($map);
    }

    public function isAvailable(): bool
    {
        $product = $this->getProduct();
        if (!$product) {
            return false;
        }

        $storeId = (int) $this->_storeManager->getStore()->getId();
        if (!$this->config->isActive($storeId)) {
            return false;
        }

        if ($product->getTypeId() !== 'simple') {
            // Configurable: always show so customers can subscribe per-variant
            // (the variant SKU is sent client-side from the option selector)
            return $product->getTypeId() === 'configurable';
        }

        $stockItem = $this->stockRegistry->getStockItem((int) $product->getId());
        return !$stockItem->getIsInStock() || $stockItem->getQty() <= 0;
    }

    public function getProduct(): ?ProductInterface
    {
        $product = $this->registry->registry('current_product');
        return $product instanceof ProductInterface ? $product : null;
    }

    public function getProductSku(): string
    {
        return (string) ($this->getProduct()?->getSku() ?? '');
    }

    public function getCustomerEmail(): string
    {
        return $this->customerSession->isLoggedIn()
            ? (string) $this->customerSession->getCustomer()->getEmail()
            : '';
    }

    public function getSubscribeUrl(): string
    {
        return $this->getUrl('stockradar/subscription/save', ['_secure' => true]);
    }
}
