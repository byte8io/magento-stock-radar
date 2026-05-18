<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model\ResourceModel;

use Byte8\Core\Model\ResourceModel\AbstractResource;
use Byte8\StockRadar\Api\Data\SubscriptionInterface;

class Subscription extends AbstractResource
{
    protected $_useIsObjectNew = true;

    protected string $_eventPrefix = SubscriptionInterface::DB_TABLE_NAME;

    protected function _construct()
    {
        $this->_init(SubscriptionInterface::DB_TABLE_NAME, SubscriptionInterface::ENTITY_ID);
    }

    /**
     * Atomic upsert: returns true if a new row was created, false if it already existed
     * (the unique key is product_id + email_hash + store_id).
     */
    public function upsertPending(
        int $productId,
        ?int $parentProductId,
        int $storeId,
        ?int $customerId,
        string $email,
        string $emailHash,
        string $unsubscribeToken,
        string $initialStatus = SubscriptionInterface::STATUS_PENDING,
        ?string $confirmationToken = null
    ): bool {
        $connection = $this->getConnection();
        $now = date('Y-m-d H:i:s');

        $rows = (int) $connection->insertOnDuplicate(
            $this->getMainTable(),
            [
                SubscriptionInterface::PRODUCT_ID => $productId,
                SubscriptionInterface::PARENT_PRODUCT_ID => $parentProductId,
                SubscriptionInterface::STORE_ID => $storeId,
                SubscriptionInterface::CUSTOMER_ID => $customerId,
                SubscriptionInterface::EMAIL => $email,
                SubscriptionInterface::EMAIL_HASH => $emailHash,
                SubscriptionInterface::UNSUBSCRIBE_TOKEN => $unsubscribeToken,
                SubscriptionInterface::CONFIRMATION_TOKEN => $confirmationToken,
                SubscriptionInterface::STATUS => $initialStatus,
                SubscriptionInterface::CREATED_AT => $now,
            ],
            [
                SubscriptionInterface::STATUS,
                SubscriptionInterface::CUSTOMER_ID,
                SubscriptionInterface::CONFIRMATION_TOKEN,
            ]
        );

        return $rows === 1;
    }

    /**
     * Returns the new subscription row by its confirmation token, or null
     * if the token doesn't match. Caller decides what to do with the result
     * — we keep the same "no leakage" pattern as unsubscribeByToken.
     *
     * @return array{entity_id:int,status:string,email:string,store_id:int}|null
     */
    public function findByConfirmationToken(string $token): ?array
    {
        if ($token === '') {
            return null;
        }

        $connection = $this->getConnection();
        $row = $connection->fetchRow(
            $connection->select()
                ->from($this->getMainTable(), [
                    SubscriptionInterface::ENTITY_ID,
                    SubscriptionInterface::STATUS,
                    SubscriptionInterface::EMAIL,
                    SubscriptionInterface::STORE_ID,
                ])
                ->where(SubscriptionInterface::CONFIRMATION_TOKEN . ' = ?', $token)
                ->limit(1)
        );

        return $row ?: null;
    }

    /**
     * GDPR delete: hard-removes every subscription row matching the email
     * hash (cascades to dispatch rows via the FK). Returns rows deleted.
     */
    public function deleteByEmailHash(string $emailHash): int
    {
        if ($emailHash === '') {
            return 0;
        }

        return (int) $this->getConnection()->delete(
            $this->getMainTable(),
            [SubscriptionInterface::EMAIL_HASH . ' = ?' => $emailHash]
        );
    }

    /**
     * Returns IDs matching any combination of filters. Caller picks what to
     * do with them — cancel, count, export. Designed for ops tooling rather
     * than per-request lookups (no pagination, no caching).
     *
     * @return int[]
     */
    public function findIdsByCriteria(
        ?string $emailHash = null,
        ?int $productId = null,
        ?int $storeId = null,
        ?string $status = null
    ): array {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [SubscriptionInterface::ENTITY_ID]);

        if ($emailHash !== null && $emailHash !== '') {
            $select->where(SubscriptionInterface::EMAIL_HASH . ' = ?', $emailHash);
        }
        if ($productId !== null) {
            $select->where(SubscriptionInterface::PRODUCT_ID . ' = ?', $productId);
        }
        if ($storeId !== null) {
            $select->where(SubscriptionInterface::STORE_ID . ' = ?', $storeId);
        }
        if ($status !== null && $status !== '') {
            $select->where(SubscriptionInterface::STATUS . ' = ?', $status);
        }

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Health snapshot for the stats CLI: counts by status, oldest pending,
     * top SKUs by pending subscriber count. One round trip per metric — fine
     * for a one-off CLI, would need denormalisation if exposed to UI.
     *
     * @return array{
     *   counts_by_status: array<string,int>,
     *   oldest_pending: ?string,
     *   top_pending_skus: array<int, array{product_id:int,count:int}>
     * }
     */
    public function getHealthSnapshot(): array
    {
        $connection = $this->getConnection();
        $table = $this->getMainTable();

        $counts = $connection->fetchPairs(
            $connection->select()
                ->from($table, [SubscriptionInterface::STATUS, 'cnt' => 'COUNT(*)'])
                ->group(SubscriptionInterface::STATUS)
        );

        $oldestPending = $connection->fetchOne(
            $connection->select()
                ->from($table, ['MIN(' . SubscriptionInterface::CREATED_AT . ')'])
                ->where(SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
        ) ?: null;

        $topSkus = $connection->fetchAll(
            $connection->select()
                ->from($table, [
                    'product_id' => SubscriptionInterface::PRODUCT_ID,
                    'count' => 'COUNT(*)',
                ])
                ->where(SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
                ->group(SubscriptionInterface::PRODUCT_ID)
                ->order('count DESC')
                ->limit(10)
        );

        return [
            'counts_by_status' => array_map('intval', $counts),
            'oldest_pending' => $oldestPending,
            'top_pending_skus' => array_map(
                static fn ($r) => ['product_id' => (int) $r['product_id'], 'count' => (int) $r['count']],
                $topSkus
            ),
        ];
    }

    public function countByEmailHash(string $emailHash): int
    {
        if ($emailHash === '') {
            return 0;
        }

        $connection = $this->getConnection();
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['COUNT(*)'])
                ->where(SubscriptionInterface::EMAIL_HASH . ' = ?', $emailHash)
        );
    }

    /**
     * Flip the listed rows to cancelled. Skips rows already in a terminal
     * state (cancelled / bounced) to keep the affected count honest.
     * Returns the number of rows actually changed.
     *
     * @param int[] $ids
     */
    public function cancelByIds(array $ids): int
    {
        $ids = array_values(array_filter(array_map('intval', $ids), static fn ($i) => $i > 0));
        if ($ids === []) {
            return 0;
        }

        return (int) $this->getConnection()->update(
            $this->getMainTable(),
            [SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_CANCELLED],
            [
                SubscriptionInterface::ENTITY_ID . ' IN (?)' => $ids,
                SubscriptionInterface::STATUS . ' NOT IN (?)' => [
                    SubscriptionInterface::STATUS_CANCELLED,
                    SubscriptionInterface::STATUS_BOUNCED,
                ],
            ]
        );
    }

    public function confirmSubscription(int $subscriptionId): int
    {
        return (int) $this->getConnection()->update(
            $this->getMainTable(),
            [
                SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_PENDING,
                SubscriptionInterface::CONFIRMATION_TOKEN => null,
            ],
            [
                SubscriptionInterface::ENTITY_ID . ' = ?' => $subscriptionId,
                SubscriptionInterface::STATUS . ' = ?' => SubscriptionInterface::STATUS_UNCONFIRMED,
            ]
        );
    }

    /**
     * Returns IDs of pending subscriptions for a product+store, oldest first.
     * Joins `catalog_product_website` so a subscription is only dispatched
     * when the product is actually assigned to the store's website — without
     * the filter, a stock-flip on store A could dispatch a subscription that
     * lives on store B (different website) where the product isn't sold,
     * giving the subscriber a broken or wrong-scope product URL.
     *
     * @return int[]
     */
    public function getPendingIdsForProduct(int $productId, int $storeId): array
    {
        $connection = $this->getConnection();
        $mainTable = $this->getMainTable();
        $storeTable = $connection->getTableName('store');
        $catalogProductWebsiteTable = $connection->getTableName('catalog_product_website');

        $select = $connection->select()
            ->from(['s' => $mainTable], [SubscriptionInterface::ENTITY_ID])
            ->joinInner(
                ['store' => $storeTable],
                's.' . SubscriptionInterface::STORE_ID . ' = store.store_id',
                []
            )
            ->joinInner(
                ['cpw' => $catalogProductWebsiteTable],
                'store.website_id = cpw.website_id AND cpw.product_id = s.' . SubscriptionInterface::PRODUCT_ID,
                []
            )
            ->where('s.' . SubscriptionInterface::PRODUCT_ID . ' = ?', $productId)
            ->where('s.' . SubscriptionInterface::STORE_ID . ' = ?', $storeId)
            ->where('s.' . SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
            ->order('s.' . SubscriptionInterface::CREATED_AT . ' ASC');

        return array_map('intval', $connection->fetchCol($select));
    }

    /**
     * Count of pending subscriptions for a product+store. Used by the
     * admin-alert threshold check after a new row is inserted — we only
     * want to fire the alert on the freshly-crossed event, so the caller
     * compares this count against the threshold.
     */
    public function countPendingForProduct(int $productId, int $storeId): int
    {
        $connection = $this->getConnection();
        return (int) $connection->fetchOne(
            $connection->select()
                ->from($this->getMainTable(), ['COUNT(*)'])
                ->where(SubscriptionInterface::PRODUCT_ID . ' = ?', $productId)
                ->where(SubscriptionInterface::STORE_ID . ' = ?', $storeId)
                ->where(SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
        );
    }

    public function markNotified(int $subscriptionId): int
    {
        return (int) $this->getConnection()->update(
            $this->getMainTable(),
            [
                SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_NOTIFIED,
                SubscriptionInterface::NOTIFIED_AT => date('Y-m-d H:i:s'),
            ],
            [SubscriptionInterface::ENTITY_ID . ' = ?' => $subscriptionId]
        );
    }

    /**
     * Cancel pending subscriptions older than the cutoff. Returns rows affected.
     */
    public function cancelExpired(string $cutoffTimestamp): int
    {
        return (int) $this->getConnection()->update(
            $this->getMainTable(),
            [SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_CANCELLED],
            [
                SubscriptionInterface::STATUS . ' = ?' => SubscriptionInterface::STATUS_PENDING,
                SubscriptionInterface::CREATED_AT . ' < ?' => $cutoffTimestamp,
            ]
        );
    }
}
