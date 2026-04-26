<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Api\Data;

interface DispatchInterface
{
    public const DB_TABLE_NAME = 'byte8_stock_radar_dispatch';

    public const ENTITY_ID = 'entity_id';
    public const SUBSCRIPTION_ID = 'subscription_id';
    public const SCHEDULED_AT = 'scheduled_at';
    public const STATUS = 'status';
    public const ATTEMPTS = 'attempts';
    public const LAST_ERROR = 'last_error';
    public const CREATED_AT = 'created_at';
    public const UPDATED_AT = 'updated_at';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';
    public const STATUS_SKIPPED = 'skipped';

    public const MAX_ATTEMPTS = 3;
}
