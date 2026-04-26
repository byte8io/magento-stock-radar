<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

interface ConfigInterface
{
    public function isActive(?int $storeId = null): bool;

    public function getThrottleWindowMinutes(?int $storeId = null): int;

    public function getSubscriptionExpiryDays(?int $storeId = null): int;

    public function getEmailSender(?int $storeId = null): string;

    public function getEmailTemplate(?int $storeId = null): string;

    public function getPingbellThreshold(?int $storeId = null): int;
}
