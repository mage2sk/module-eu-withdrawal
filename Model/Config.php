<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Central configuration reader for Panth_EuWithdrawal.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const XML_PATH = 'panth_euwithdrawal/';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    private function flag(string $path, ?int $storeId = null): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    private function value(string $path, ?int $storeId = null): string
    {
        return (string)$this->scopeConfig->getValue(self::XML_PATH . $path, ScopeInterface::SCOPE_STORE, $storeId);
    }

    public function isEnabled(?int $storeId = null): bool
    {
        return $this->flag('general/enabled', $storeId);
    }

    public function getButtonLabel(?int $storeId = null): string
    {
        return $this->value('general/button_label', $storeId) ?: 'Cancel my order';
    }

    /**
     * @return string[]
     */
    public function getPlacement(?int $storeId = null): array
    {
        $raw = $this->value('general/placement', $storeId);
        if ($raw === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public function getFloatSide(?int $storeId = null): string
    {
        $side = $this->value('general/float_side', $storeId);
        return $side === 'left' ? 'left' : 'right';
    }

    public function getPeriodDays(?int $storeId = null): int
    {
        $days = (int)$this->value('general/period_days', $storeId);
        return $days > 0 ? $days : 14;
    }

    public function getPeriodBasis(?int $storeId = null): string
    {
        return $this->value('general/period_basis', $storeId) ?: 'order';
    }

    public function getOrderStatus(?int $storeId = null): string
    {
        return $this->value('general/order_status', $storeId);
    }

    public function askReason(?int $storeId = null): bool
    {
        return $this->flag('form/ask_reason', $storeId);
    }

    public function isHoneypotEnabled(?int $storeId = null): bool
    {
        return $this->flag('form/enable_honeypot', $storeId);
    }

    public function getRateLimit(?int $storeId = null): int
    {
        return (int)$this->value('form/rate_limit', $storeId);
    }

    public function getIntroText(?int $storeId = null): string
    {
        return $this->value('compliance/intro_text', $storeId);
    }

    public function getExcludedProductsText(?int $storeId = null): string
    {
        return $this->value('compliance/excluded_products_text', $storeId);
    }

    public function getReturnShippingText(?int $storeId = null): string
    {
        return $this->value('compliance/return_shipping_text', $storeId);
    }

    public function getRefundPolicyText(?int $storeId = null): string
    {
        return $this->value('compliance/refund_policy_text', $storeId);
    }

    public function getSenderIdentity(?int $storeId = null): string
    {
        return $this->value('email/sender_identity', $storeId) ?: 'general';
    }

    public function sendCustomerConfirmation(?int $storeId = null): bool
    {
        return $this->flag('email/send_customer_confirmation', $storeId);
    }

    public function getCustomerTemplate(?int $storeId = null): string
    {
        return $this->value('email/customer_template', $storeId) ?: 'panth_euwithdrawal_email_customer_template';
    }

    public function sendAdminNotification(?int $storeId = null): bool
    {
        return $this->flag('email/send_admin_notification', $storeId);
    }

    public function getRecipientEmail(?int $storeId = null): string
    {
        return $this->value('email/recipient_email', $storeId);
    }

    public function getAdminTemplate(?int $storeId = null): string
    {
        return $this->value('email/admin_template', $storeId) ?: 'panth_euwithdrawal_email_admin_template';
    }

    public function injectOrderEmailLink(?int $storeId = null): bool
    {
        return $this->flag('email/inject_order_email_link', $storeId);
    }

    public function isCronEnabled(?int $storeId = null): bool
    {
        return $this->flag('batch/cron_enabled', $storeId);
    }

    public function getBatchSize(?int $storeId = null): int
    {
        $size = (int)$this->value('batch/batch_size', $storeId);
        return $size > 0 ? $size : 50;
    }

    public function isRefundReminderEnabled(?int $storeId = null): bool
    {
        return $this->flag('batch/refund_reminder_enabled', $storeId);
    }

    public function getRefundReminderDays(?int $storeId = null): int
    {
        $days = (int)$this->value('batch/refund_reminder_days', $storeId);
        return $days > 0 ? $days : 10;
    }
}
