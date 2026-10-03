<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    use ConfigStubTrait;

    public function testDefaultsWhenNothingIsConfigured(): void
    {
        $config = $this->makeConfig();

        $this->assertFalse($config->isEnabled());
        $this->assertSame('Cancel my order', $config->getButtonLabel());
        $this->assertSame([], $config->getPlacement());
        $this->assertSame('right', $config->getFloatSide());
        $this->assertSame(14, $config->getPeriodDays());
        $this->assertSame('order', $config->getPeriodBasis());
        $this->assertSame('', $config->getOrderStatus());
        $this->assertSame(0, $config->getRateLimit());
        $this->assertSame(5, $config->getReferenceAttemptLimit());
        $this->assertSame('general', $config->getSenderIdentity());
        $this->assertSame('panth_euwithdrawal_email_customer_template', $config->getCustomerTemplate());
        $this->assertSame('panth_euwithdrawal_email_admin_template', $config->getAdminTemplate());
        $this->assertSame('panth_euwithdrawal_email_status_template', $config->getStatusTemplate());
        $this->assertSame(50, $config->getBatchSize());
        $this->assertSame(10, $config->getRefundReminderDays());
        $this->assertSame('', $config->getRecipientEmail());
    }

    public function testConfiguredValuesWin(): void
    {
        $config = $this->makeConfig([
            'general/button_label' => 'Withdraw',
            'general/period_days' => '30',
            'general/period_basis' => 'shipment',
            'general/order_status' => 'holded',
            'form/rate_limit' => '7',
            'email/sender_identity' => 'sales',
            'email/customer_template' => 'c_tpl',
            'email/admin_template' => 'a_tpl',
            'email/status_template' => 's_tpl',
            'email/recipient_email' => 'ops@example.test',
            'batch/batch_size' => '200',
            'batch/refund_reminder_days' => '3',
            'compliance/intro_text' => 'intro',
            'compliance/excluded_products_text' => 'excluded',
            'compliance/return_shipping_text' => 'shipping',
            'compliance/refund_policy_text' => 'refund',
        ]);

        $this->assertSame('Withdraw', $config->getButtonLabel());
        $this->assertSame(30, $config->getPeriodDays());
        $this->assertSame('shipment', $config->getPeriodBasis());
        $this->assertSame('holded', $config->getOrderStatus());
        $this->assertSame(7, $config->getRateLimit());
        $this->assertSame('sales', $config->getSenderIdentity());
        $this->assertSame('c_tpl', $config->getCustomerTemplate());
        $this->assertSame('a_tpl', $config->getAdminTemplate());
        $this->assertSame('s_tpl', $config->getStatusTemplate());
        $this->assertSame('ops@example.test', $config->getRecipientEmail());
        $this->assertSame(200, $config->getBatchSize());
        $this->assertSame(3, $config->getRefundReminderDays());
        $this->assertSame('intro', $config->getIntroText());
        $this->assertSame('excluded', $config->getExcludedProductsText());
        $this->assertSame('shipping', $config->getReturnShippingText());
        $this->assertSame('refund', $config->getRefundPolicyText());
    }

    public function testNonPositiveNumbersFallBackToDefaults(): void
    {
        $config = $this->makeConfig([
            'general/period_days' => '0',
            'batch/batch_size' => '-5',
            'batch/refund_reminder_days' => 'abc',
        ]);

        $this->assertSame(14, $config->getPeriodDays());
        $this->assertSame(50, $config->getBatchSize());
        $this->assertSame(10, $config->getRefundReminderDays());
    }

    public function testPlacementIsTrimmedAndEmptyEntriesDropped(): void
    {
        $config = $this->makeConfig(['general/placement' => ' floating , ,footer,']);

        $this->assertSame(['floating', 'footer'], $config->getPlacement());
    }

    public function testFloatSideOnlyAcceptsLeftOtherwiseRight(): void
    {
        $this->assertSame('left', $this->makeConfig(['general/float_side' => 'left'])->getFloatSide());
        $this->assertSame('right', $this->makeConfig(['general/float_side' => 'top'])->getFloatSide());
    }

    public function testReferenceAttemptLimitAllowsZeroAndClampsNegatives(): void
    {
        $this->assertSame(0, $this->makeConfig(['form/reference_attempt_limit' => '0'])->getReferenceAttemptLimit());
        $this->assertSame(0, $this->makeConfig(['form/reference_attempt_limit' => '-3'])->getReferenceAttemptLimit());
        $this->assertSame(9, $this->makeConfig(['form/reference_attempt_limit' => '9'])->getReferenceAttemptLimit());
        $this->assertSame(5, $this->makeConfig(['form/reference_attempt_limit' => '  '])->getReferenceAttemptLimit());
    }

    public function testFlagsReadTheExpectedPaths(): void
    {
        $config = $this->makeConfig([], [
            'general/enabled' => true,
            'form/ask_reason' => true,
            'form/enable_honeypot' => true,
            'email/send_customer_confirmation' => true,
            'email/send_admin_notification' => true,
            'email/notify_status_change' => true,
            'email/inject_order_email_link' => true,
            'batch/cron_enabled' => true,
            'batch/refund_reminder_enabled' => true,
        ]);

        $this->assertTrue($config->isEnabled());
        $this->assertTrue($config->askReason());
        $this->assertTrue($config->isHoneypotEnabled());
        $this->assertTrue($config->sendCustomerConfirmation());
        $this->assertTrue($config->sendAdminNotification());
        $this->assertTrue($config->notifyStatusChange());
        $this->assertTrue($config->injectOrderEmailLink());
        $this->assertTrue($config->isCronEnabled());
        $this->assertTrue($config->isRefundReminderEnabled());
    }

    public function testStoreScopeAndIdArePassedThrough(): void
    {
        $calls = [];
        $scope = $this->createStub(ScopeConfigInterface::class);
        $scope->method('isSetFlag')->willReturnCallback(function ($path, $type, $store) use (&$calls) {
            $calls[] = [$path, $type, $store];
            return true;
        });
        $scope->method('getValue')->willReturnCallback(function ($path, $type, $store) use (&$calls) {
            $calls[] = [$path, $type, $store];
            return null;
        });
        $config = new Config($scope);

        $config->isEnabled(3);
        $config->getButtonLabel(4);

        $this->assertSame(['panth_euwithdrawal/general/enabled', ScopeInterface::SCOPE_STORE, 3], $calls[0]);
        $this->assertSame(['panth_euwithdrawal/general/button_label', ScopeInterface::SCOPE_STORE, 4], $calls[1]);
    }
}
