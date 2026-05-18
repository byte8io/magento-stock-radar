<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\Exception\LocalizedException;

/**
 * Sliding fixed-window counters in the default cache for the two subscribe
 * abuse vectors: too many attempts from one IP, and too many subscribes for
 * a single email address. Cheap and good enough — we don't need millisecond
 * precision, just a roof on the rate.
 */
class RateLimiter
{
    private const CACHE_TAG = 'BYTE8_STOCK_RADAR_RATE';
    private const IP_WINDOW_SECONDS = 300;
    private const EMAIL_WINDOW_SECONDS = 3600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly ConfigInterface $config,
        private readonly EmailHasher $emailHasher
    ) {
    }

    public function assertWithinLimits(string $email, ?string $ipAddress, ?int $storeId): void
    {
        if (!$this->config->isRateLimitEnabled($storeId)) {
            return;
        }

        if ($ipAddress !== null && $ipAddress !== '') {
            $this->bump(
                'ip:' . sha1($ipAddress),
                self::IP_WINDOW_SECONDS,
                $this->config->getRateLimitPerIp($storeId),
                __('You are subscribing too quickly. Please try again in a few minutes.')
            );
        }

        if ($email !== '') {
            $this->bump(
                'email:' . $this->emailHasher->hash($email),
                self::EMAIL_WINDOW_SECONDS,
                $this->config->getRateLimitPerEmail($storeId),
                __('This email has reached its hourly subscribe limit. Please try again later.')
            );
        }
    }

    private function bump(string $key, int $windowSeconds, int $maxHits, \Magento\Framework\Phrase $rejectMessage): void
    {
        $cacheKey = 'byte8_stock_radar_rl_' . $key;
        $current = (int) $this->cache->load($cacheKey);
        if ($current >= $maxHits) {
            throw new LocalizedException($rejectMessage);
        }

        $this->cache->save(
            (string) ($current + 1),
            $cacheKey,
            [self::CACHE_TAG],
            $windowSeconds
        );
    }
}
