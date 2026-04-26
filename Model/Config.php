<?php
/**
 * Copyright © Byte8 Ltd. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Byte8\StockRadar\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config implements ConfigInterface
{
    private const XML_PATH_ACTIVE = 'byte8_stock_radar/general/active';
    private const XML_PATH_THROTTLE = 'byte8_stock_radar/dispatch/throttle_minutes';
    private const XML_PATH_EXPIRY = 'byte8_stock_radar/dispatch/expiry_days';
    private const XML_PATH_EMAIL_SENDER = 'byte8_stock_radar/email/sender';
    private const XML_PATH_EMAIL_TEMPLATE = 'byte8_stock_radar/email/template';
    private const XML_PATH_PINGBELL_THRESHOLD = 'byte8_stock_radar/pingbell/threshold';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function isActive(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getThrottleWindowMinutes(?int $storeId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_THROTTLE, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getSubscriptionExpiryDays(?int $storeId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_EXPIRY, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getEmailSender(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getPingbellThreshold(?int $storeId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_PINGBELL_THRESHOLD, ScopeInterface::SCOPE_STORE, $storeId));
    }
}
