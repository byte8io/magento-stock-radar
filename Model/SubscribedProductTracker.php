<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Framework\Session\SessionManagerInterface;

/**
 * Stores "SKUs the current visitor has already subscribed to" in the catalog
 * session so the PDP can render an "already subscribed" state on reload
 * rather than re-prompting an empty form.
 *
 * Keyed by store_id → list of SKUs to keep multi-store visitors honest.
 */
class SubscribedProductTracker
{
    private const SESSION_KEY = 'byte8_stock_radar_subscribed';
    private const MAX_TRACKED = 100;

    public function __construct(
        private readonly SessionManagerInterface $session
    ) {
    }

    public function remember(string $sku, int $storeId): void
    {
        if ($sku === '') {
            return;
        }

        $all = $this->load();
        $bucket = $all[$storeId] ?? [];
        if (!in_array($sku, $bucket, true)) {
            $bucket[] = $sku;
            if (count($bucket) > self::MAX_TRACKED) {
                $bucket = array_slice($bucket, -self::MAX_TRACKED);
            }
            $all[$storeId] = $bucket;
            $this->session->setData(self::SESSION_KEY, $all);
        }
    }

    public function isSubscribed(string $sku, int $storeId): bool
    {
        if ($sku === '') {
            return false;
        }

        $all = $this->load();
        return in_array($sku, $all[$storeId] ?? [], true);
    }

    /**
     * @return string[]
     */
    public function getSubscribedSkus(int $storeId): array
    {
        $all = $this->load();
        return $all[$storeId] ?? [];
    }

    public function forget(string $sku, int $storeId): void
    {
        $all = $this->load();
        if (!isset($all[$storeId])) {
            return;
        }
        $all[$storeId] = array_values(array_filter($all[$storeId], static fn ($s) => $s !== $sku));
        $this->session->setData(self::SESSION_KEY, $all);
    }

    /**
     * @return array<int, string[]>
     */
    private function load(): array
    {
        $raw = $this->session->getData(self::SESSION_KEY);
        return is_array($raw) ? $raw : [];
    }
}
