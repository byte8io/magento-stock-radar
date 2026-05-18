<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Api\Data;

interface SubscriptionInterface
{
    public const DB_TABLE_NAME = 'byte8_stock_radar_subscription';

    public const ENTITY_ID = 'entity_id';
    public const PRODUCT_ID = 'product_id';
    public const PARENT_PRODUCT_ID = 'parent_product_id';
    public const STORE_ID = 'store_id';
    public const CUSTOMER_ID = 'customer_id';
    public const EMAIL = 'email';
    public const EMAIL_HASH = 'email_hash';
    public const UNSUBSCRIBE_TOKEN = 'unsubscribe_token';
    public const CONFIRMATION_TOKEN = 'confirmation_token';
    public const STATUS = 'status';
    public const CREATED_AT = 'created_at';
    public const NOTIFIED_AT = 'notified_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_UNCONFIRMED = 'unconfirmed';
    public const STATUS_PENDING = 'pending';
    public const STATUS_NOTIFIED = 'notified';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_BOUNCED = 'bounced';
}
