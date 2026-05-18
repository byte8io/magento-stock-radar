<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Cron;

use Byte8\StockRadar\Model\ConfigInterface;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;

/**
 * Cancels pending subscriptions older than the configured expiry. Runs nightly.
 * Expiry is global — uses the default-store config so we don't need to scope
 * per-website here.
 */
class ExpireSubscriptions
{
    public function __construct(
        private readonly ConfigInterface $config,
        private readonly SubscriptionResource $subscriptionResource
    ) {
    }

    public function execute(): int
    {
        $days = $this->config->getSubscriptionExpiryDays();
        if ($days <= 0) {
            return 0;
        }

        $cutoff = date('Y-m-d H:i:s', strtotime(sprintf('-%d days', $days)));
        return $this->subscriptionResource->cancelExpired($cutoff);
    }
}
