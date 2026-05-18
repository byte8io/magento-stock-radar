<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Block\Product\View;

use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\Stock\StockChecker;
use Byte8\StockRadar\Model\SubscribedProductTracker;
use Magento\Catalog\Api\Data\ProductInterface;
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
        private readonly StockChecker $stockChecker,
        private readonly CustomerSession $customerSession,
        private readonly SerializerInterface $serializer,
        private readonly SubscribedProductTracker $subscribedTracker,
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

        if ($product->getTypeId() === ConfigurableType::TYPE_CODE) {
            // Configurable: only mount the widget if at least one variant is
            // currently OOS. The form itself starts hidden — the swatch
            // mixin shows it after the user clicks an OOS option.
            return $this->configurableHasAnyOosVariant($product);
        }

        if ($product->getTypeId() !== 'simple') {
            return false;
        }

        return !$this->stockChecker->isInStock(
            (string) $product->getSku(),
            (int) $this->_storeManager->getStore()->getId()
        );
    }

    /**
     * True for configurable products — used by the template to start the form
     * hidden and let the swatch-renderer mixin reveal it on OOS-option click.
     */
    public function isConfigurable(): bool
    {
        $product = $this->getProduct();
        return $product !== null && $product->getTypeId() === ConfigurableType::TYPE_CODE;
    }

    private function configurableHasAnyOosVariant(ProductInterface $product): bool
    {
        $typeInstance = $product->getTypeInstance();
        if (!$typeInstance instanceof ConfigurableType) {
            return false;
        }

        $storeId = (int) $this->_storeManager->getStore()->getId();
        foreach ($typeInstance->getUsedProducts($product) as $variant) {
            if (!$this->stockChecker->isInStock((string) $variant->getSku(), $storeId)) {
                return true;
            }
        }
        return false;
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

    public function isHoneypotEnabled(): bool
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->config->isHoneypotEnabled($storeId);
    }

    /**
     * Returns SKUs the visitor has already subscribed to in this session so the
     * template can show "you'll be notified" copy instead of an empty form on
     * page reload. Also surfaced to JS so it can flip state after a successful
     * subscribe without a reload.
     *
     * @return string[]
     */
    public function getSessionSubscribedSkus(): array
    {
        $storeId = (int) $this->_storeManager->getStore()->getId();
        return $this->subscribedTracker->getSubscribedSkus($storeId);
    }

    public function getSessionSubscribedSkusJson(): string
    {
        return $this->serializer->serialize($this->getSessionSubscribedSkus());
    }

    /**
     * True if the current product (or any of its configurable simples) is
     * already remembered in this session. The template uses this to render
     * the confirmation panel server-side so reload is honoured.
     */
    public function isAlreadySubscribed(): bool
    {
        $skus = $this->getSessionSubscribedSkus();
        if ($skus === []) {
            return false;
        }

        $product = $this->getProduct();
        if (!$product) {
            return false;
        }

        if (in_array((string) $product->getSku(), $skus, true)) {
            return true;
        }

        if ($product->getTypeId() === ConfigurableType::TYPE_CODE) {
            $typeInstance = $product->getTypeInstance();
            if ($typeInstance instanceof ConfigurableType) {
                foreach ($typeInstance->getUsedProducts($product) as $variant) {
                    if (in_array((string) $variant->getSku(), $skus, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
