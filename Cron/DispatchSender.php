<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Cron;

use Byte8\StockRadar\Api\Data\DispatchInterface;
use Byte8\StockRadar\Api\Data\SubscriptionInterface;
use Byte8\StockRadar\Model\Notifier;
use Byte8\StockRadar\Model\ResourceModel\Dispatch as DispatchResource;
use Byte8\StockRadar\Model\ResourceModel\Subscription as SubscriptionResource;
use Psr\Log\LoggerInterface;

/**
 * Drains the dispatch queue every minute. Sends one batch per run; fetched
 * limit keeps a single tick bounded so a backlog can't starve other crons.
 *
 * Failures bump the attempts counter and re-queue until MAX_ATTEMPTS is hit,
 * then the dispatch row is marked failed and stays for admin inspection.
 */
class DispatchSender
{
    private const BATCH_LIMIT = 200;

    public function __construct(
        private readonly DispatchResource $dispatchResource,
        private readonly SubscriptionResource $subscriptionResource,
        private readonly Notifier $notifier,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Cron entry point. Magento's ProcessCronQueueObserver passes the
     * Schedule as the first positional arg (`call_user_func_array($callback,
     * [$schedule])`), so the cron-facing signature must accept *any* extra
     * arg — we declare no typed params and ignore whatever's passed.
     *
     * All real work lives in `drain()` so the CLI's `dispatch:run --force`
     * can pass `$ignoreSchedule = true` without confusing the cron.
     */
    public function execute(): void
    {
        $this->drain(false);
    }

    /**
     * Drain the dispatch queue. Returns counts so callers can surface
     * useful feedback.
     *
     * `$ignoreSchedule = true` drops the scheduled_at filter so every
     * queued row is processed regardless of throttle offset. Reserved for
     * the CLI's `dispatch:run --force` — the cron must continue to honour
     * scheduled_at so the throttle window actually staggers the blast.
     *
     * @return array{fetched:int,sent:int,failed:int}
     */
    public function drain(bool $ignoreSchedule = false): array
    {
        $rows = $this->dispatchResource->fetchDueDispatchRows(self::BATCH_LIMIT, $ignoreSchedule);
        $sent = 0;
        $failed = 0;
        if ($rows === []) {
            return ['fetched' => 0, 'sent' => 0, 'failed' => 0];
        }

        foreach ($rows as $row) {
            $dispatchId = (int) $row['dispatch_id'];
            $attempts = (int) $row['attempts'] + 1;

            try {
                $this->notifier->notify(
                    (int) $row[SubscriptionInterface::PRODUCT_ID],
                    (int) $row[SubscriptionInterface::STORE_ID],
                    (string) $row[SubscriptionInterface::EMAIL],
                    (string) $row[SubscriptionInterface::UNSUBSCRIBE_TOKEN]
                );
                $this->dispatchResource->markSent($dispatchId);
                $this->subscriptionResource->markNotified((int) $row['subscription_id']);
                $sent++;
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    'Byte8 StockRadar dispatch %d failed (attempt %d): %s',
                    $dispatchId,
                    $attempts,
                    $e->getMessage()
                ));
                $this->dispatchResource->recordFailure($dispatchId, $attempts, $e->getMessage());
                $failed++;
            }
        }

        return ['fetched' => count($rows), 'sent' => $sent, 'failed' => $failed];
    }
}
