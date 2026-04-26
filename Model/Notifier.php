<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslation;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sends a single back-in-stock email. Called by the Cron worker per dispatch row.
 *
 * Throws on any failure so the cron can record the attempt and retry; never
 * swallows errors silently.
 */
class Notifier
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly InlineTranslation $inlineTranslation,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly State $appState
    ) {
    }

    public function notify(
        int $productId,
        int $storeId,
        string $email,
        string $unsubscribeToken
    ): void {
        $product = $this->productRepository->getById($productId, false, $storeId);
        $store = $this->storeManager->getStore($storeId);

        $unsubscribeUrl = $this->urlBuilder->getUrl(
            'stockradar/subscription/unsubscribe',
            ['token' => $unsubscribeToken, '_scope' => $storeId, '_nosid' => true]
        );

        $this->inlineTranslation->suspend();
        try {
            $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use (
                $product,
                $store,
                $email,
                $unsubscribeUrl,
                $storeId
            ) {
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($this->config->getEmailTemplate($storeId))
                    ->setTemplateOptions([
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ])
                    ->setTemplateVars([
                        'product' => $product,
                        'product_name' => $product->getName(),
                        'product_url' => $product->getProductUrl(),
                        'product_sku' => $product->getSku(),
                        'store' => $store,
                        'store_name' => $store->getFrontendName() ?: $store->getName(),
                        'unsubscribe_url' => $unsubscribeUrl,
                    ])
                    ->setFromByScope($this->config->getEmailSender($storeId), $storeId)
                    ->addTo($email)
                    ->getTransport();

                $transport->sendMessage();
            });
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
