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

    public function getAdminAlertThreshold(?int $storeId = null): int;

    public function isAdminAlertBellEnabled(?int $storeId = null): bool;

    public function isAdminAlertEmailEnabled(?int $storeId = null): bool;

    /**
     * Returns the configured recipient, or the General Contact email when
     * the field is blank, or empty string when neither resolves.
     */
    public function getAdminAlertRecipientEmail(?int $storeId = null): string;

    public function getAdminAlertEmailSender(?int $storeId = null): string;

    public function getAdminAlertEmailTemplate(?int $storeId = null): string;

    public function isRateLimitEnabled(?int $storeId = null): bool;

    public function getRateLimitPerIp(?int $storeId = null): int;

    public function getRateLimitPerEmail(?int $storeId = null): int;

    public function isHoneypotEnabled(?int $storeId = null): bool;

    public function isCreatedFlagHidden(?int $storeId = null): bool;

    public function isCaptchaEnabled(?int $storeId = null): bool;

    public function isDoubleOptinEnabled(?int $storeId = null): bool;

    public function getConfirmationEmailTemplate(?int $storeId = null): string;
}
