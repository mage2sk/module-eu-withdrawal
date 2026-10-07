<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Registry;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Model\Order;
use Panth\EuWithdrawal\Block\Account\Withdrawals;
use Panth\EuWithdrawal\Block\Link;
use Panth\EuWithdrawal\Block\Order\WithdrawButton;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;

class FrontendBlocksTest extends TestCase
{
    use ConfigStubTrait;

    private array $urlCalls = [];

    private function context(): Context
    {
        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(function ($route = '', $params = []) {
            $this->urlCalls[] = [$route, $params];
            return 'https://shop.test/' . $route;
        });
        $context = $this->createStub(Context::class);
        $context->method('getUrlBuilder')->willReturn($url);
        return $context;
    }

    private function link(string $slot, array $values, array $flags): Link
    {
        return new Link($this->context(), $this->makeConfig($values, $flags), ['slot' => $slot]);
    }

    public function testLinkOnlyShowsForConfiguredSlot(): void
    {
        $values = ['general/placement' => 'header,footer'];
        $on = ['general/enabled' => true];

        $this->assertTrue($this->link('footer', $values, $on)->canShow());
        $this->assertFalse($this->link('floating', $values, $on)->canShow());
        $this->assertFalse($this->link('', $values, $on)->canShow());
        $this->assertFalse($this->link('footer', $values, [])->canShow());
    }

    public function testMobileLinkReplacesFloatingButtonOnlyWithoutFooterLink(): void
    {
        $on = ['general/enabled' => true];

        $this->assertTrue($this->link('mobile', ['general/placement' => 'floating,account'], $on)->canShow());
        $this->assertFalse($this->link('mobile', ['general/placement' => 'floating,footer'], $on)->canShow());
        $this->assertFalse($this->link('mobile', ['general/placement' => 'header,account'], $on)->canShow());
        $this->assertFalse($this->link('mobile', ['general/placement' => 'floating'], [])->canShow());
    }

    public function testLinkAccessors(): void
    {
        $link = $this->link('floating', ['general/button_label' => 'Go', 'general/float_side' => 'left'], []);

        $this->assertSame('floating', $link->getSlot());
        $this->assertSame('Go', $link->getLabel());
        $this->assertSame('left', $link->getFloatSide());
        $this->assertSame('https://shop.test/withdrawal', $link->getHref());
    }

    private function button(?object $order, array $flags, ?WithdrawalService $service = null): WithdrawButton
    {
        $registry = $this->createStub(Registry::class);
        $registry->method('registry')->willReturnCallback(fn($key) => $key === 'current_order' ? $order : null);
        $deployment = $this->createStub(DeploymentConfig::class);
        $deployment->method('get')->willReturn('blk');

        return new WithdrawButton(
            $this->context(),
            $registry,
            $this->makeConfig(['general/button_label' => 'Cancel'], $flags),
            $service ?? $this->createStub(WithdrawalService::class),
            new TokenManager($deployment)
        );
    }

    private function order(string $increment = '000000031'): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getIncrementId')->willReturn($increment);
        $order->method('getStoreId')->willReturn(2);
        $order->method('getCustomerEmail')->willReturn('c@example.test');
        return $order;
    }

    private function serviceReturning(bool $eligible, bool $existing): WithdrawalService
    {
        $service = $this->createStub(WithdrawalService::class);
        $service->method('isOrderEligible')->willReturn($eligible);
        $service->method('hasExistingRequest')->willReturn($existing);
        return $service;
    }

    public function testButtonEligibility(): void
    {
        $on = ['general/enabled@2' => true];

        $this->assertTrue($this->button($this->order(), $on, $this->serviceReturning(true, false))->isEligible());
        $this->assertFalse($this->button($this->order(), $on, $this->serviceReturning(true, true))->isEligible());
        $this->assertFalse($this->button($this->order(), $on, $this->serviceReturning(false, false))->isEligible());
        $this->assertFalse($this->button($this->order(), [], $this->serviceReturning(true, false))->isEligible());
        $this->assertFalse($this->button($this->order(''), $on, $this->serviceReturning(true, false))->isEligible());
        $this->assertFalse($this->button(null, $on, $this->serviceReturning(true, false))->isEligible());
        $this->assertFalse($this->button(new \stdClass(), $on, $this->serviceReturning(true, false))->isEligible());
    }

    public function testButtonSwallowsServiceErrors(): void
    {
        $service = $this->createStub(WithdrawalService::class);
        $service->method('isOrderEligible')->willThrowException(new \RuntimeException('x'));

        $this->assertFalse($this->button($this->order(), ['general/enabled' => true], $service)->isEligible());
    }

    public function testButtonUrlCarriesSignedToken(): void
    {
        $button = $this->button($this->order(), []);

        $this->assertSame('https://shop.test/withdrawal', $button->getWithdrawUrl());
        $this->assertSame('Cancel', $button->getLabel());
        [$route, $params] = $this->urlCalls[0];
        $this->assertSame('withdrawal', $route);
        $this->assertSame([
            'o' => '000000031',
            'e' => 'c@example.test',
            't' => hash_hmac('sha256', '000000031|c@example.test', 'blk'),
        ], $params['_query']);
    }

    public function testButtonUrlWithoutOrderIsPlainForm(): void
    {
        $this->button(null, [])->getWithdrawUrl();

        $this->assertSame([['withdrawal', []]], $this->urlCalls);
    }

    private function withdrawals(array &$customerFilters, int &$creates): Withdrawals
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addCustomerFilter')->willReturnCallback(
            function ($id) use (&$customerFilters, $collection) {
                $customerFilters[] = $id;
                return $collection;
            }
        );
        $collection->method('setOrder')->willReturnSelf();
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturnCallback(function () use (&$creates, $collection) {
            $creates++;
            return $collection;
        });
        $session = $this->createStub(CustomerSession::class);
        $session->method('getCustomerId')->willReturn('12');
        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(\DateTimeInterface $dt) => $dt->format('d/m/Y')
        );

        return new Withdrawals(
            $this->context(),
            $session,
            $factory,
            $timezone,
            $this->makeConfig(['general/button_label' => 'Withdraw']),
        );
    }

    public function testWithdrawalsCollectionIsScopedToCustomerAndMemoised(): void
    {
        $filters = [];
        $creates = 0;
        $block = $this->withdrawals($filters, $creates);

        $first = $block->getRequests();
        $this->assertSame($first, $block->getRequests());
        $this->assertSame(1, $creates);
        $this->assertSame([12], $filters);
    }

    public function testWithdrawalsHelpers(): void
    {
        $filters = [];
        $creates = 0;
        $block = $this->withdrawals($filters, $creates);

        $this->assertSame('Acknowledged', $block->getStatusLabel(2));
        $this->assertSame('Received', $block->getStatusLabel(0));
        $this->assertSame('', $block->formatRequestedAt(null));
        $this->assertSame('bad', $block->formatRequestedAt('bad'));
        $this->assertSame('02/03/2026', $block->formatRequestedAt('2026-03-02 10:00:00'));
        $this->assertSame('Withdraw', $block->getButtonLabel());
        $this->assertSame('https://shop.test/withdrawal', $block->getNewWithdrawalUrl());
        $block->getViewUrl(5);
        $this->assertSame(['withdrawal/account/view', ['request_id' => 5]], end($this->urlCalls));
    }
}
