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
        string $unsubscribeToken
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
                SubscriptionInterface::STATUS => SubscriptionInterface::STATUS_PENDING,
                SubscriptionInterface::CREATED_AT => $now,
            ],
            [
                SubscriptionInterface::STATUS,
                SubscriptionInterface::CUSTOMER_ID,
            ]
        );

        return $rows === 1;
    }

    /**
     * Returns IDs of pending subscriptions for a product+store, oldest first.
     *
     * @return int[]
     */
    public function getPendingIdsForProduct(int $productId, int $storeId): array
    {
        $connection = $this->getConnection();
        $select = $connection->select()
            ->from($this->getMainTable(), [SubscriptionInterface::ENTITY_ID])
            ->where(SubscriptionInterface::PRODUCT_ID . ' = ?', $productId)
            ->where(SubscriptionInterface::STORE_ID . ' = ?', $storeId)
            ->where(SubscriptionInterface::STATUS . ' = ?', SubscriptionInterface::STATUS_PENDING)
            ->order(SubscriptionInterface::CREATED_AT . ' ASC');

        return array_map('intval', $connection->fetchCol($select));
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
