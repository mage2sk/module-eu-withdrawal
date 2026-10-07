<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller\Index;

use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Framework\View\Result\Page;
use Magento\Sales\Api\Data\OrderInterface;
use Panth\EuWithdrawal\Controller\Index\Lookup;
use Panth\EuWithdrawal\Model\BotGuard;
use Panth\EuWithdrawal\Model\Request;
use Panth\EuWithdrawal\Model\WithdrawalContext;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Test\Unit\Controller\ControllerTestCase;

class LookupTest extends ControllerTestCase
{
    private const NOT_FOUND = 'We could not find an order matching those details. '
        . 'Please check your order number and email address.';

    private array $values = [];
    private array $flags = [];
    private ?OrderInterface $order = null;
    private bool $hasExisting = false;
    private ?Request $existing = null;
    private ?string $ineligible = null;
    private array $lookups = [];
    private WithdrawalContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->values = ['form/rate_limit' => '10'];
        $this->flags = ['general/enabled' => true];
        $this->order = $this->createStub(OrderInterface::class);
        $this->context = new WithdrawalContext();
        $this->params = [
            'increment_id' => '000000060',
            'email' => 'jane@example.test',
            'name' => ' Jane ',
            'reason' => 'colour',
        ];
    }

    private function controller(): Lookup
    {
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturn('10.2.2.2');

        $service = $this->createStub(WithdrawalService::class);
        $service->method('findOrder')->willReturnCallback(function ($inc, $email) {
            $this->lookups[] = [$inc, $email];
            return $this->order;
        });
        $service->method('hasExistingRequest')->willReturnCallback(fn() => $this->hasExisting);
        $service->method('getExistingRequest')->willReturnCallback(fn() => $this->existing);
        $service->method('getIneligibilityReason')->willReturnCallback(
            fn() => $this->ineligible === null ? null : __($this->ineligible)
        );

        $config = $this->makeConfig($this->values, $this->flags);
        return new Lookup(
            $this->httpRequest(),
            $this->redirectFactory(),
            $this->pageFactory(),
            $this->messageManager(),
            $this->persistor(),
            $remote,
            $config,
            $service,
            $this->tokenManager(),
            $this->rateLimiter(),
            new BotGuard($config),
            $this->context
        );
    }

    public function testDisabledModuleRedirectsToNoRoute(): void
    {
        $this->flags = [];
        $this->controller()->execute();

        $this->assertSame('noroute', $this->redirectPath);
    }

    public function testEligibleOrderRendersConfirmPageWithContext(): void
    {
        $this->flags['form/ask_reason'] = true;
        $this->params['token'] = $this->token('000000060', 'jane@example.test');

        $result = $this->controller()->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['withdrawal_index_confirm'], $this->handles);
        $this->assertSame('Confirm your withdrawal', $this->title);
        $this->assertSame($this->order, $this->context->getOrder());
        $this->assertSame('Jane', $this->context->getName());
        $this->assertSame('jane@example.test', $this->context->getEmail());
        $this->assertSame('colour', $this->context->getReason());
        $this->assertSame($this->params['token'], $this->context->getToken());
    }

    public function testReasonIgnoredWhenNotAsked(): void
    {
        $this->controller()->execute();

        $this->assertSame('', $this->context->getReason());
    }

    public function testBotGetsGenericFailureAndFormIsRetained(): void
    {
        $this->flags['form/enable_honeypot'] = true;
        $this->params['contact_url'] = 'x';

        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(['error', self::NOT_FOUND], $this->lastMessage());
        $this->assertSame(
            ['increment_id' => '000000060', 'email' => 'jane@example.test', 'name' => 'Jane', 'reason' => ''],
            $this->persisted['panth_euwithdrawal_form']
        );
        $this->assertSame([], $this->lookups);
    }

    public function testIpRateLimitKeepsFormData(): void
    {
        $this->values['form/rate_limit'] = '1';
        $controller = $this->controller();
        $controller->execute();
        $controller->execute();

        $this->assertSame(['error', 'Too many attempts. Please wait a few minutes and try again.'], $this->lastMessage());
        $this->assertSame('000000060', $this->persisted['panth_euwithdrawal_form']['increment_id']);
        $this->assertCount(1, $this->lookups);
    }

    public function testInvalidInputFailsWithoutLookup(): void
    {
        $this->params['email'] = 'nope';
        $this->controller()->execute();

        $this->assertSame(['error', self::NOT_FOUND], $this->lastMessage());
        $this->assertSame([], $this->lookups);
        $this->assertSame('nope', $this->persisted['panth_euwithdrawal_form']['email']);
    }

    public function testUnknownOrderCountsTowardsReferenceLock(): void
    {
        $this->values['form/reference_attempt_limit'] = '2';
        $this->order = null;
        $controller = $this->controller();
        $controller->execute();
        $controller->execute();
        $this->assertSame(['error', self::NOT_FOUND], $this->lastMessage());

        $controller->execute();

        $this->assertSame(
            ['error', 'Too many attempts for this order number. Please try again later or contact us.'],
            $this->lastMessage()
        );
        $this->assertCount(2, $this->lookups);
    }

    public function testForgedTokenFailsWithoutLookup(): void
    {
        $this->params['token'] = 'bad';
        $this->controller()->execute();

        $this->assertSame(['error', self::NOT_FOUND], $this->lastMessage());
        $this->assertSame([], $this->lookups);
    }

    public function testExistingRequestShowsStatusPage(): void
    {
        $this->hasExisting = true;
        $this->existing = $this->makeRequest(['request_id' => 4]);

        $result = $this->controller()->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame(['withdrawal_index_status'], $this->handles);
        $this->assertSame('Withdrawal status', $this->title);
        $this->assertSame($this->existing, $this->context->getStatusRequest());
        $this->assertNull($this->context->getOrder());
    }

    public function testExistingRequestThatCannotBeLoadedShowsNotice(): void
    {
        $this->hasExisting = true;

        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(
            ['notice', 'A withdrawal request for this order has already been received.'],
            $this->lastMessage()
        );
    }

    public function testIneligibleOrderShowsReason(): void
    {
        $this->ineligible = 'The withdrawal period for this order has expired.';

        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(['error', $this->ineligible], $this->lastMessage());
        $this->assertNull($this->context->getOrder());
    }
}
