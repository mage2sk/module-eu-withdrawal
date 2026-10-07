<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Plugin;

use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Sales\Block\Order\Email\Items;
use Magento\Sales\Model\Order;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Plugin\OrderEmailWithdrawalLink;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class OrderEmailWithdrawalLinkTest extends TestCase
{
    use ConfigStubTrait;

    private array $urlCalls = [];
    private array $warnings = [];
    /** null makes the eligibility check throw */
    private ?bool $eligible = true;

    private function plugin(array $values = [], array $flags = []): OrderEmailWithdrawalLink
    {
        $flags += ['general/enabled' => true, 'email/inject_order_email_link' => true];

        $deployment = $this->createStub(DeploymentConfig::class);
        $deployment->method('get')->willReturn('secret');

        $service = $this->createStub(WithdrawalService::class);
        $service->method('isOrderEligible')->willReturnCallback(function () {
            if ($this->eligible === null) {
                throw new \RuntimeException('boom');
            }
            return $this->eligible;
        });

        $url = $this->createStub(UrlInterface::class);
        $url->method('getUrl')->willReturnCallback(function ($route, $params) {
            $this->urlCalls[] = [$route, $params];
            return 'https://shop.test/withdrawal/?o=' . $params['_query']['o'] . '&t=' . $params['_query']['t'];
        });

        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES));
        $escaper->method('escapeUrl')->willReturnCallback(static fn($v) => htmlspecialchars((string)$v, ENT_QUOTES));

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($m) {
            $this->warnings[] = $m;
        });

        return new OrderEmailWithdrawalLink(
            $this->makeConfig($values, $flags),
            new TokenManager($deployment),
            $service,
            $url,
            $escaper,
            $logger
        );
    }

    private function items(?Order $order): Items
    {
        $items = $this->createStub(Items::class);
        $items->method('getOrder')->willReturn($order);
        return $items;
    }

    private function order(string $increment = '000000077'): Order
    {
        $order = $this->createStub(Order::class);
        $order->method('getIncrementId')->willReturn($increment);
        $order->method('getStoreId')->willReturn(3);
        $order->method('getCustomerEmail')->willReturn('jane@example.test');
        return $order;
    }

    public function testAppendsWithdrawalLinkForEligibleOrder(): void
    {
        $html = $this->plugin(['general/button_label' => 'Cancel <now>'])
            ->afterToHtml($this->items($this->order()), '<p>items</p>');

        $this->assertStringStartsWith('<p>items</p><table', $html);
        $this->assertStringContainsString('Cancel &lt;now&gt;', $html);
        $this->assertStringContainsString('Changed your mind?', $html);

        [$route, $params] = $this->urlCalls[0];
        $this->assertSame('withdrawal', $route);
        $this->assertSame(3, $params['_scope']);
        $this->assertTrue($params['_nosid']);
        $this->assertSame('000000077', $params['_query']['o']);
        $this->assertSame('jane@example.test', $params['_query']['e']);
        $this->assertSame(hash_hmac('sha256', '000000077|jane@example.test', 'secret'), $params['_query']['t']);
        $this->assertStringContainsString('href="https://shop.test/withdrawal/?o=000000077&amp;t=', $html);
    }

    public function testLeavesOutputAloneWithoutOrderOrIncrementId(): void
    {
        $this->assertSame('x', $this->plugin()->afterToHtml($this->items(null), 'x'));
        $this->assertSame('x', $this->plugin()->afterToHtml($this->items($this->order('')), 'x'));
        $this->assertSame([], $this->urlCalls);
    }

    public function testLeavesOutputAloneWhenDisabledOrInjectionOff(): void
    {
        $this->assertSame('x', $this->plugin([], ['general/enabled' => false])->afterToHtml($this->items($this->order()), 'x'));
        $this->assertSame(
            'x',
            $this->plugin([], ['email/inject_order_email_link' => false])->afterToHtml($this->items($this->order()), 'x')
        );
    }

    public function testLeavesOutputAloneForIneligibleOrder(): void
    {
        $this->eligible = false;

        $this->assertSame('x', $this->plugin()->afterToHtml($this->items($this->order()), 'x'));
        $this->assertSame([], $this->urlCalls);
    }

    public function testErrorsAreLoggedAndOriginalOutputKept(): void
    {
        $this->eligible = null;

        $this->assertSame('x', $this->plugin()->afterToHtml($this->items($this->order()), 'x'));
        $this->assertStringContainsString('order-email link failed: boom', $this->warnings[0]);
    }
}
