<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\ResourceModel;

use Byte8\Core\Model\ResourceModel\AbstractResource;
use Byte8\StockRadar\Api\Data\DispatchInterface;
use Byte8\StockRadar\Api\Data\SubscriptionInterface;

class Dispatch extends AbstractResource
{
    protected $_useIsObjectNew = true;

    protected string $_eventPrefix = DispatchInterface::DB_TABLE_NAME;

    protected function _construct()
    {
        $this->_init(DispatchInterface::DB_TABLE_NAME, DispatchInterface::ENTITY_ID);
    }

    /**
     * Bulk-insert dispatch rows for a list of subscription IDs, each with a
     * randomly-staggered scheduled_at within [now, now + windowMinutes].
     *
     * @param int[] $subscriptionIds
     */
    public function enqueueBatch(array $subscriptionIds, int $windowMinutes): int
    {
        if ($subscriptionIds === []) {
            return 0;
        }

        $now = time();
        $windowSeconds = max(0, $windowMinutes) * 60;
        $rows = [];
        foreach ($subscriptionIds as $subscriptionId) {
            $offset = $windowSeconds > 0 ? random_int(0, $windowSeconds) : 0;
            $rows[] = [
                DispatchInterface::SUBSCRIPTION_ID => (int) $subscriptionId,
                DispatchInterface::SCHEDULED_AT => date('Y-m-d H:i:s', $now + $offset),
                DispatchInterface::STATUS => DispatchInterface::STATUS_QUEUED,
            ];
        }

        return (int) $this->getConnection()->insertMultiple($this->getMainTable(), $rows);
    }

    /**
     * Fetch up to $limit rows due for sending, with their joined subscription data.
     * Returns plain arrays — the cron drains the queue as a flat batch and doesn't
     * need full model hydration.
     */
    public function fetchDueDispatchRows(int $limit): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from(
                ['d' => $this->getMainTable()],
                [
                    'dispatch_id' => DispatchInterface::ENTITY_ID,
                    'attempts' => DispatchInterface::ATTEMPTS,
                ]
            )
            ->joinInner(
                ['s' => $this->getTable(SubscriptionInterface::DB_TABLE_NAME)],
                'd.' . DispatchInterface::SUBSCRIPTION_ID . ' = s.' . SubscriptionInterface::ENTITY_ID,
                [
                    'subscription_id' => SubscriptionInterface::ENTITY_ID,
                    SubscriptionInterface::PRODUCT_ID,
                    SubscriptionInterface::PARENT_PRODUCT_ID,
                    SubscriptionInterface::STORE_ID,
                    SubscriptionInterface::CUSTOMER_ID,
                    SubscriptionInterface::EMAIL,
                    SubscriptionInterface::UNSUBSCRIBE_TOKEN,
                ]
            )
            ->where('d.' . DispatchInterface::STATUS . ' = ?', DispatchInterface::STATUS_QUEUED)
            ->where('s.' . SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
            ->where('d.' . DispatchInterface::SCHEDULED_AT . ' <= ?', date('Y-m-d H:i:s'))
            ->order('d.' . DispatchInterface::SCHEDULED_AT . ' ASC')
            ->limit($limit);

        return $connection->fetchAll($select);
    }

    public function markSent(int $dispatchId): void
    {
        $this->getConnection()->update(
            $this->getMainTable(),
            [DispatchInterface::STATUS => DispatchInterface::STATUS_SENT],
            [DispatchInterface::ENTITY_ID . ' = ?' => $dispatchId]
        );
    }

    public function recordFailure(int $dispatchId, int $attempts, string $error): void
    {
        $status = $attempts >= DispatchInterface::MAX_ATTEMPTS
            ? DispatchInterface::STATUS_FAILED
            : DispatchInterface::STATUS_QUEUED;

        $this->getConnection()->update(
            $this->getMainTable(),
            [
                DispatchInterface::STATUS => $status,
                DispatchInterface::ATTEMPTS => $attempts,
                DispatchInterface::LAST_ERROR => mb_substr($error, 0, 1024),
            ],
            [DispatchInterface::ENTITY_ID . ' = ?' => $dispatchId]
        );
    }
}
