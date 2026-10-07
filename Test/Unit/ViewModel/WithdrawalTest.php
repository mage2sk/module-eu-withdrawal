<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\ViewModel;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Request\DataPersistorInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Data\Form\FormKey;
use Magento\Framework\DataObject;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Sales\Api\Data\OrderInterface;
use Magento\Sales\Model\Order;
use Panth\Core\Helper\Theme;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalContext;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use Panth\EuWithdrawal\ViewModel\Withdrawal;
use PHPUnit\Framework\TestCase;

class WithdrawalTest extends TestCase
{
    use ConfigStubTrait;
    use RequestModelTrait;

    private array $params = [];
    private array $persisted = [];
    private array $timezoneCalls = [];
    private WithdrawalContext $context;
    private ?WithdrawalService $service = null;

    protected function setUp(): void
    {
        $this->context = new WithdrawalContext();
    }

    private function viewModel(array $values = [], array $flags = []): Withdrawal
    {
        $theme = $this->createStub(Theme::class);
        $theme->method('isHyva')->willReturn(true);

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(static fn($route) => 'https://shop.test/' . $route . '/');

        $request = $this->createStub(RequestInterface::class);
        $request->method('getParam')->willReturnCallback(fn($k, $d = null) => $this->params[$k] ?? $d);

        $persistor = $this->createStub(DataPersistorInterface::class);
        $persistor->method('get')->willReturnCallback(fn($k) => $this->persisted[$k] ?? null);
        $persistor->method('clear')->willReturnCallback(function ($k) {
            unset($this->persisted[$k]);
        });

        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(function ($date, $dateType, $timeType) {
            $this->timezoneCalls[] = [$dateType, $timeType];
            return 'TZ ' . ($date instanceof \DateTimeInterface ? $date->format('Y-m-d H:i') : $date);
        });

        $deployment = $this->createStub(DeploymentConfig::class);
        $deployment->method('get')->willReturn('vm-key');

        $formKey = $this->createStub(FormKey::class);
        $formKey->method('getFormKey')->willReturn('fk123');

        return new Withdrawal(
            $this->makeConfig($values, $flags),
            $theme,
            $url,
            $request,
            $persistor,
            $timezone,
            new TokenManager($deployment),
            $this->service ?? $this->createStub(WithdrawalService::class),
            $this->context,
            $formKey
        );
    }

    public function testSimpleDelegates(): void
    {
        $vm = $this->viewModel(
            [
                'general/button_label' => 'Withdraw',
                'general/period_days' => '21',
                'compliance/intro_text' => 'intro',
                'compliance/excluded_products_text' => 'ex',
                'compliance/return_shipping_text' => 'ship',
                'compliance/refund_policy_text' => 'ref',
            ],
            ['general/enabled' => true, 'form/ask_reason' => true, 'form/enable_honeypot' => true]
        );

        $this->assertSame('fk123', $vm->getFormKey());
        $this->assertTrue($vm->isHyva());
        $this->assertTrue($vm->isEnabled());
        $this->assertSame('Withdraw', $vm->getButtonLabel());
        $this->assertSame('Withdraw', $vm->getPageTitle());
        $this->assertSame(21, $vm->getPeriodDays());
        $this->assertTrue($vm->askReason());
        $this->assertTrue($vm->isHoneypotEnabled());
        $this->assertSame(['intro', 'ex', 'ship', 'ref'], [
            $vm->getIntroText(),
            $vm->getExcludedProductsText(),
            $vm->getReturnShippingText(),
            $vm->getRefundPolicyText(),
        ]);
    }

    public function testUrls(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('https://shop.test/withdrawal/', $vm->getFormUrl());
        $this->assertSame('https://shop.test/withdrawal/index/lookup/', $vm->getLookupUrl());
        $this->assertSame('https://shop.test/withdrawal/index/submit/', $vm->getSubmitUrl());
        $this->assertSame('https://shop.test/withdrawal/customer/orders/', $vm->getCustomerOrdersUrl());
    }

    public function testPrefillFromRetainedFormIsOneShotAndDropsToken(): void
    {
        $this->persisted['panth_euwithdrawal_form'] = ['increment_id' => 7, 'email' => 'a@example.test', 'name' => 'A'];
        $this->params = ['o' => '1', 'e' => 'x@example.test', 't' => 'tok'];
        $vm = $this->viewModel();

        $this->assertSame(
            ['increment_id' => '7', 'email' => 'a@example.test', 'name' => 'A', 'reason' => '', 'token' => ''],
            $vm->getPrefill()
        );
        $this->assertSame([], $this->persisted);
    }

    public function testPrefillFromSignedLink(): void
    {
        $token = hash_hmac('sha256', '000000009|b@example.test', 'vm-key');
        $this->params = ['o' => ' 000000009 ', 'e' => 'b@example.test', 't' => $token];

        $this->assertSame(
            ['increment_id' => '000000009', 'email' => 'b@example.test', 'name' => '', 'reason' => '', 'token' => $token],
            $this->viewModel()->getPrefill()
        );
    }

    public function testPrefillIgnoresUnsignedLink(): void
    {
        $this->params = ['o' => '000000009', 'e' => 'b@example.test', 't' => 'forged'];

        $this->assertSame(
            ['increment_id' => '', 'email' => '', 'name' => '', 'reason' => '', 'token' => ''],
            $this->viewModel()->getPrefill()
        );
    }

    public function testContextAccessors(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('getAllVisibleItems')->willReturn([new DataObject(['sku' => 'S'])]);
        $this->context->set($order, 'Jane', 'j@example.test', 'tok', 'why');
        $this->context->setProof('WDR-1', '000000001', 'p@example.test');
        $status = $this->makeRequest(['request_id' => 1]);
        $this->context->setStatusRequest($status);
        $vm = $this->viewModel();

        $this->assertSame($order, $vm->getOrder());
        $this->assertSame(['Jane', 'j@example.test', 'why', 'tok'], [
            $vm->getContextName(),
            $vm->getContextEmail(),
            $vm->getContextReason(),
            $vm->getContextToken(),
        ]);
        $this->assertCount(1, $vm->getOrderItems());
        $this->assertSame(['WDR-1', '000000001', 'p@example.test'], [
            $vm->getProofReference(),
            $vm->getProofIncrementId(),
            $vm->getProofEmail(),
        ]);
        $this->assertSame($status, $vm->getStatusRequest());
    }

    public function testNoOrderMeansNoItemsDeadlineOrStoreFormatting(): void
    {
        $vm = $this->viewModel();

        $this->assertSame([], $vm->getOrderItems());
        $this->assertSame('', $vm->getDeadlineFormatted());
        $this->assertSame('1,234.50', $vm->formatPrice(1234.5));
    }

    public function testFormatPriceUsesOrderCurrencyWithoutMarkup(): void
    {
        $order = $this->createStub(Order::class);
        $order->method('formatPrice')->willReturn('<span class="price"> 12,00 EUR </span>');
        $this->context->set($order, 'n', 'e@example.test');

        $this->assertSame('12,00 EUR', $this->viewModel()->formatPrice(12.0));
    }

    public function testFormatPriceFallsBackForOrdersWithoutFormatter(): void
    {
        $this->context->set($this->createStub(OrderInterface::class), 'n', 'e@example.test');

        $this->assertSame('3.10', $this->viewModel()->formatPrice(3.1));
    }

    public function testDeadlineIsFormattedLongDateOnly(): void
    {
        $order = $this->createStub(OrderInterface::class);
        $this->context->set($order, 'n', 'e@example.test');
        $service = $this->createStub(WithdrawalService::class);
        $service->method('getDeadline')->willReturn(new \DateTimeImmutable('2026-06-15 08:00:00', new \DateTimeZone('UTC')));
        $this->service = $service;

        $this->assertSame('TZ 2026-06-15 08:00:00', $this->viewModel()->getDeadlineFormatted());
        $this->assertSame([[\IntlDateFormatter::LONG, \IntlDateFormatter::NONE]], $this->timezoneCalls);
    }

    public function testStatusLabelFallsBackToReceived(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('Rejected', $vm->getStatusLabel(4));
        $this->assertSame('Received', $vm->getStatusLabel(42));
    }

    public function testFormatStoredDate(): void
    {
        $vm = $this->viewModel();

        $this->assertSame('', $vm->formatStoredDate(null));
        $this->assertSame('', $vm->formatStoredDate(''));
        $this->assertSame('garbage', $vm->formatStoredDate('garbage'));
        $this->assertSame('TZ 2026-01-01 05:06', $vm->formatStoredDate('2026-01-01 05:06:07'));
    }
}
