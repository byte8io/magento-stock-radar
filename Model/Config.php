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
    private const XML_PATH_ADMIN_ALERT_THRESHOLD = 'byte8_stock_radar/admin_alert/threshold';
    private const XML_PATH_ADMIN_ALERT_BELL_ENABLED = 'byte8_stock_radar/admin_alert/bell_enabled';
    private const XML_PATH_ADMIN_ALERT_EMAIL_ENABLED = 'byte8_stock_radar/admin_alert/email_enabled';
    private const XML_PATH_ADMIN_ALERT_RECIPIENT_EMAIL = 'byte8_stock_radar/admin_alert/recipient_email';
    private const XML_PATH_ADMIN_ALERT_EMAIL_SENDER = 'byte8_stock_radar/admin_alert/email_sender';
    private const XML_PATH_ADMIN_ALERT_EMAIL_TEMPLATE = 'byte8_stock_radar/admin_alert/email_template';
    private const XML_PATH_GENERAL_CONTACT_EMAIL = 'trans_email/ident_general/email';
    private const XML_PATH_RATE_LIMIT_ENABLED = 'byte8_stock_radar/security/rate_limit_enabled';
    private const XML_PATH_RATE_LIMIT_PER_IP = 'byte8_stock_radar/security/rate_limit_per_ip';
    private const XML_PATH_RATE_LIMIT_PER_EMAIL = 'byte8_stock_radar/security/rate_limit_per_email';
    private const XML_PATH_HONEYPOT_ENABLED = 'byte8_stock_radar/security/honeypot_enabled';
    private const XML_PATH_HIDE_CREATED_FLAG = 'byte8_stock_radar/security/hide_created_flag';
    private const XML_PATH_CAPTCHA_ENABLED = 'byte8_stock_radar/security/captcha_enabled';
    private const XML_PATH_DOUBLE_OPTIN_ENABLED = 'byte8_stock_radar/security/double_optin_enabled';
    private const XML_PATH_CONFIRMATION_EMAIL_TEMPLATE = 'byte8_stock_radar/security/confirmation_email_template';

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

    public function getAdminAlertThreshold(?int $storeId = null): int
    {
        return max(0, (int) $this->scopeConfig->getValue(self::XML_PATH_ADMIN_ALERT_THRESHOLD, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function isAdminAlertBellEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ADMIN_ALERT_BELL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isAdminAlertEmailEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ADMIN_ALERT_EMAIL_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAdminAlertRecipientEmail(?int $storeId = null): string
    {
        $configured = trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_ADMIN_ALERT_RECIPIENT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        ));
        if ($configured !== '') {
            return $configured;
        }

        // Fall back to the store's General Contact email so a fresh install
        // works without the admin having to retype an address they already
        // configured under Stores → Configuration → General.
        return (string) $this->scopeConfig->getValue(
            self::XML_PATH_GENERAL_CONTACT_EMAIL,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function getAdminAlertEmailSender(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_ADMIN_ALERT_EMAIL_SENDER, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getAdminAlertEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_ADMIN_ALERT_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isRateLimitEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_RATE_LIMIT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getRateLimitPerIp(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_RATE_LIMIT_PER_IP, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function getRateLimitPerEmail(?int $storeId = null): int
    {
        return max(1, (int) $this->scopeConfig->getValue(self::XML_PATH_RATE_LIMIT_PER_EMAIL, ScopeInterface::SCOPE_STORE, $storeId));
    }

    public function isHoneypotEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HONEYPOT_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCreatedFlagHidden(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_HIDE_CREATED_FLAG, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isCaptchaEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_CAPTCHA_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isDoubleOptinEnabled(?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_DOUBLE_OPTIN_ENABLED, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function getConfirmationEmailTemplate(?int $storeId = null): string
    {
        return (string) $this->scopeConfig->getValue(self::XML_PATH_CONFIRMATION_EMAIL_TEMPLATE, ScopeInterface::SCOPE_STORE, $storeId);
    }
}
