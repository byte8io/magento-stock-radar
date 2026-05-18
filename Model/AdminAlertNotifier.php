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
use Magento\Framework\Notification\NotifierInterface;
use Magento\Framework\Translate\Inline\StateInterface as InlineTranslation;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Posts a "high-demand SKU" alert when the pending subscriber count for a
 * single product first crosses the configured threshold. Two transports,
 * both admin-toggleable:
 *
 *  - **Bell-icon notification** via Magento's built-in NotifierInterface
 *    (top-right inbox in the admin header).
 *  - **Transactional email** to the configured admin recipient — reaches
 *    the admin even when they're not in the admin panel.
 *
 * Either transport can be disabled independently. Each is best-effort:
 * a failure on one never blocks the other, and neither failure ever
 * breaks the subscribe path.
 */
class AdminAlertNotifier
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly NotifierInterface $notifier,
        private readonly UrlInterface $backendUrl,
        private readonly TransportBuilder $transportBuilder,
        private readonly InlineTranslation $inlineTranslation,
        private readonly StoreManagerInterface $storeManager,
        private readonly State $appState,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Call after a fresh pending row has just been inserted (or after a
     * confirmation flip from UNCONFIRMED to PENDING). Posts the alert only
     * if this insert was the one that took the count from below threshold
     * to at-or-above — once per crossing.
     */
    public function maybeNotifyThresholdCrossed(
        ProductInterface $product,
        int $storeId,
        int $newCount
    ): void {
        $threshold = $this->config->getAdminAlertThreshold($storeId);
        if ($threshold <= 0) {
            return;
        }

        // Fire only on the freshly-crossed event.
        if ($newCount < $threshold || ($newCount - 1) >= $threshold) {
            return;
        }

        if ($this->config->isAdminAlertBellEnabled($storeId)) {
            try {
                $this->postBellNotification($product, $storeId, $newCount, $threshold);
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Byte8 StockRadar admin bell alert failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }

        if ($this->config->isAdminAlertEmailEnabled($storeId)) {
            try {
                $this->sendEmail($product, $storeId, $newCount, $threshold);
            } catch (\Throwable $e) {
                $this->logger->warning(
                    'Byte8 StockRadar admin email alert failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
        }
    }

    private function postBellNotification(
        ProductInterface $product,
        int $storeId,
        int $newCount,
        int $threshold
    ): void {
        $demandUrl = $this->backendUrl->getUrl('byte8/demand/index');

        $this->notifier->addMajor(
            (string) __(
                'Stock Radar: %1 customers waiting for %2',
                $newCount,
                $product->getName() ?: $product->getSku()
            ),
            (string) __(
                'SKU %1 has just crossed the high-demand threshold of %2 pending subscribers on store %3. Review the Demand Heatmap: %4',
                $product->getSku(),
                $threshold,
                $storeId,
                $demandUrl
            )
        );
    }

    private function sendEmail(
        ProductInterface $product,
        int $storeId,
        int $newCount,
        int $threshold
    ): void {
        $recipient = $this->config->getAdminAlertRecipientEmail($storeId);
        if ($recipient === '') {
            // No recipient resolvable — skip silently. Admin-facing alert,
            // not a customer-impacting failure.
            return;
        }

        $store = $this->storeManager->getStore($storeId);
        $demandUrl = $this->backendUrl->getUrl('byte8/demand/index');

        $this->inlineTranslation->suspend();
        try {
            // Build inside emulated area for correct template render context;
            // send OUTSIDE — Magento_CustomerSampleData's MailPlugin no-ops
            // sendMessage when isAreaCodeEmulated() is true.
            $transport = null;
            $this->appState->emulateAreaCode(Area::AREA_FRONTEND, function () use (
                $product,
                $store,
                $storeId,
                $newCount,
                $threshold,
                $recipient,
                $demandUrl,
                &$transport
            ) {
                $transport = $this->transportBuilder
                    ->setTemplateIdentifier($this->config->getAdminAlertEmailTemplate($storeId))
                    ->setTemplateOptions([
                        'area' => Area::AREA_FRONTEND,
                        'store' => $storeId,
                    ])
                    ->setTemplateVars([
                        'product' => $product,
                        'product_name' => $product->getName(),
                        'product_sku' => $product->getSku(),
                        'product_url' => $product->getProductUrl(),
                        'subscriber_count' => $newCount,
                        'threshold' => $threshold,
                        'store' => $store,
                        'store_name' => $store->getFrontendName() ?: $store->getName(),
                        'store_id' => $storeId,
                        'demand_url' => $demandUrl,
                    ])
                    ->setFromByScope($this->config->getAdminAlertEmailSender($storeId), $storeId)
                    ->addTo($recipient)
                    ->getTransport();
            });

            $transport->sendMessage();
        } finally {
            $this->inlineTranslation->resume();
        }
    }
}
