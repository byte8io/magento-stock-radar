<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\Framework\App\Area;
use Magento\Framework\App\State;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslation;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Sends the double opt-in confirmation email. Same transport pattern as
 * Notifier — emulates the frontend area and uses the standard
 * TransportBuilder, so any SMTP module in the stack handles delivery.
 */
class ConfirmationSender
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly TransportBuilder $transportBuilder,
        private readonly InlineTranslation $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly State $appState
    ) {
    }

    public function send(
        ProductInterface $product,
        string $email,
        string $confirmationToken,
        int $storeId
    ): void {
        $store = $this->storeManager->getStore($storeId);

        $confirmUrl = $this->urlBuilder->getUrl(
            'stockradar/subscription/confirm',
            ['token' => $confirmationToken, '_scope' => $storeId, '_nosid' => true]
        );

        $this->inlineTranslation->suspend();
        try {
            // Build inside emulated area for correct template render context;
            // send OUTSIDE — Magento_CustomerSampleData's MailPlugin no-ops
            // sendMessage when isAreaCodeEmulated() is true.
            $transport = null;
            $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use (
                $product,
                $store,
                $email,
                $confirmUrl,
                $storeId,
                &$transport
            ) {
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($this->config->getConfirmationEmailTemplate($storeId))
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
                        'confirm_url' => $confirmUrl,
                    ])
                    ->setFromByScope($this->config->getEmailSender($storeId), $storeId)
                    ->addTo($email)
                    ->getTransport();
            });

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
