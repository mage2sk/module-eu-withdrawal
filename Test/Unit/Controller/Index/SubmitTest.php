<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller\Index;

use Magento\Framework\Exception\AlreadyExistsException;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\HTTP\PhpEnvironment\RemoteAddress;
use Magento\Sales\Api\Data\OrderInterface;
use Panth\EuWithdrawal\Controller\Index\Submit;
use Panth\EuWithdrawal\Model\BotGuard;
use Panth\EuWithdrawal\Model\WithdrawalService;
use Panth\EuWithdrawal\Test\Unit\Controller\ControllerTestCase;
use Psr\Log\LoggerInterface;

class SubmitTest extends ControllerTestCase
{
    private array $values = [];
    private array $flags = [];
    private string $ip = '10.1.1.1';
    private ?OrderInterface $order = null;
    private ?\Throwable $submitError = null;
    private array $submitted = [];
    private array $lookups = [];
    private array $errors = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->values = ['form/rate_limit' => '10'];
        $this->flags = ['general/enabled' => true];
        $this->order = $this->createStub(OrderInterface::class);
        $this->params = [
            'increment_id' => ' 000000050 ',
            'email' => 'jane@example.test',
            'name' => "Jane\x07 Doe",
            'reason' => 'too big',
        ];
    }

    private function controller(): Submit
    {
        $remote = $this->createStub(RemoteAddress::class);
        $remote->method('getRemoteAddress')->willReturnCallback(fn() => $this->ip);

        $service = $this->createStub(WithdrawalService::class);
        $service->method('findOrder')->willReturnCallback(function ($inc, $email) {
            $this->lookups[] = [$inc, $email];
            return $this->order;
        });
        $service->method('submit')->willReturnCallback(function ($order, $data) {
            if ($this->submitError) {
                throw $this->submitError;
            }
            $this->submitted[] = $data;
            return $this->makeRequest([
                'proof_reference' => 'WDR-XYZ',
                'increment_id' => '000000050',
                'customer_email' => $data['email'],
            ]);
        });

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($m) {
            $this->errors[] = $m;
        });

        $config = $this->makeConfig($this->values, $this->flags);
        return new Submit(
            $this->httpRequest(),
            $this->redirectFactory(),
            $this->messageManager(),
            $this->persistor(),
            $remote,
            $config,
            $service,
            $this->tokenManager(),
            new BotGuard($config),
            $logger,
            $this->rateLimiter()
        );
    }

    public function testDisabledModuleRedirectsToNoRoute(): void
    {
        $this->flags = [];
        $this->controller()->execute();

        $this->assertSame('noroute', $this->redirectPath);
        $this->assertSame([], $this->submitted);
    }

    public function testHappyPathSubmitsAndStoresProof(): void
    {
        $this->server['HTTP_USER_AGENT'] = 'UnitBrowser/1.0';
        $this->flags['form/ask_reason'] = true;

        $this->controller()->execute();

        $this->assertSame('withdrawal/index/success', $this->redirectPath);
        $this->assertSame([['000000050', 'jane@example.test']], $this->lookups);
        $this->assertSame([
            'name' => 'Jane  Doe',
            'email' => 'jane@example.test',
            'reason' => 'too big',
            'ip' => '10.1.1.1',
            'user_agent' => 'UnitBrowser/1.0',
        ], $this->submitted[0]);
        $this->assertSame(
            ['proof_reference' => 'WDR-XYZ', 'increment_id' => '000000050', 'email' => 'jane@example.test'],
            $this->persisted['panth_euwithdrawal_proof']
        );
    }

    public function testReasonIsDroppedWhenNotAsked(): void
    {
        $this->controller()->execute();

        $this->assertSame('', $this->submitted[0]['reason']);
    }

    public function testNameIsCappedAt255Characters(): void
    {
        $this->params['name'] = str_repeat('n', 300);
        $this->controller()->execute();

        $this->assertSame(255, mb_strlen($this->submitted[0]['name']));
    }

    public function testBotIsSilentlyRedirected(): void
    {
        $this->flags['form/enable_honeypot'] = true;
        $this->params['contact_url'] = 'filled';

        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame([], $this->messages);
        $this->assertSame([], $this->lookups);
    }

    public function testIpRateLimitStopsSubmission(): void
    {
        $this->values['form/rate_limit'] = '1';
        $controller = $this->controller();
        $controller->execute();
        $this->assertSame('withdrawal/index/success', $this->redirectPath);

        $controller->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(['error', 'Too many attempts. Please wait a few minutes and try again.'], $this->lastMessage());
        $this->assertCount(1, $this->submitted);
    }

    public function testEmptyIpSkipsRateLimiting(): void
    {
        $this->ip = '';
        $this->values['form/rate_limit'] = '1';
        $controller = $this->controller();
        $controller->execute();
        $controller->execute();

        $this->assertCount(2, $this->submitted);
        $this->assertSame('', $this->submitted[0]['ip']);
    }

    /**
     * @return array<string, array{0: array}>
     */
    public static function invalidFieldsProvider(): array
    {
        return [
            'missing increment' => [['increment_id' => '']],
            'missing email' => [['email' => '']],
            'bad email' => [['email' => 'not-an-email']],
            'blank name' => [['name' => "\x01\x02 "]],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidFieldsProvider')]
    public function testRequiredFieldsAreValidated(array $override): void
    {
        $this->params = array_merge($this->params, $override);
        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(['error', 'Please complete all required fields.'], $this->lastMessage());
        $this->assertSame([], $this->lookups);
    }

    public function testLockedReferenceIsRefused(): void
    {
        $this->values['form/reference_attempt_limit'] = '1';
        $this->order = null;
        $controller = $this->controller();
        $controller->execute();
        $this->assertSame(['error', 'We could not find an order matching those details.'], $this->lastMessage());

        $controller->execute();

        $this->assertSame(
            ['error', 'Too many attempts for this order number. Please try again later or contact us.'],
            $this->lastMessage()
        );
        $this->assertCount(1, $this->lookups);
    }

    public function testInvalidTokenRegistersFailureWithoutLookup(): void
    {
        $this->values['form/reference_attempt_limit'] = '1';
        $this->params['token'] = 'forged';
        $controller = $this->controller();
        $controller->execute();

        $this->assertSame(['error', 'We could not verify your request. Please start again.'], $this->lastMessage());
        $this->assertSame([], $this->lookups);

        $this->params['token'] = $this->token('000000050', 'jane@example.test');
        $controller->execute();
        $this->assertStringStartsWith('Too many attempts for this order number', $this->lastMessage()[1]);
    }

    public function testValidTokenProceeds(): void
    {
        $this->params['token'] = $this->token('000000050', 'Jane@Example.test');

        $this->controller()->execute();

        $this->assertSame('withdrawal/index/success', $this->redirectPath);
    }

    public function testDuplicateRequestShowsNotice(): void
    {
        $this->submitError = new AlreadyExistsException(__('already there'));
        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(['notice', 'already there'], $this->lastMessage());
        $this->assertArrayNotHasKey('panth_euwithdrawal_proof', $this->persisted);
    }

    public function testIneligibleOrderShowsError(): void
    {
        $this->submitError = new LocalizedException(__('expired'));
        $this->controller()->execute();

        $this->assertSame(['error', 'expired'], $this->lastMessage());
    }

    public function testUnexpectedErrorIsLoggedWithGenericMessage(): void
    {
        $this->submitError = new \RuntimeException('deadlock');
        $this->controller()->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame(
            ['error', 'Something went wrong while processing your withdrawal. Please try again.'],
            $this->lastMessage()
        );
        $this->assertStringContainsString('submit failed: deadlock', $this->errors[0]);
    }
}
