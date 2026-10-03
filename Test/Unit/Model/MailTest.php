<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Mail\TransportInterface;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Store\Model\StoreManagerInterface;
use Panth\EuWithdrawal\Model\Mail;
use Panth\EuWithdrawal\Model\Request;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class MailTest extends TestCase
{
    use ConfigStubTrait;
    use RequestModelTrait;

    private array $sent = [];
    private array $current = [];
    private ?\Throwable $sendError = null;
    private array $errors = [];
    private ?StateInterface $inline = null;

    private function mail(array $values = []): Mail
    {
        $builder = $this->createStub(TransportBuilder::class);
        $builder->method('setTemplateIdentifier')->willReturnCallback(function ($id) use ($builder) {
            $this->current = ['template' => $id];
            return $builder;
        });
        $builder->method('setTemplateOptions')->willReturnCallback(function ($options) use ($builder) {
            $this->current['options'] = $options;
            return $builder;
        });
        $builder->method('setTemplateVars')->willReturnCallback(function ($vars) use ($builder) {
            $this->current['vars'] = $vars;
            return $builder;
        });
        $builder->method('setFromByScope')->willReturnCallback(function ($from, $store) use ($builder) {
            $this->current['from'] = [$from, $store];
            return $builder;
        });
        $builder->method('addTo')->willReturnCallback(function ($email, $name) use ($builder) {
            $this->current['to'] = [$email, $name];
            return $builder;
        });
        $transport = $this->createStub(TransportInterface::class);
        $transport->method('sendMessage')->willReturnCallback(function () {
            if ($this->sendError) {
                throw $this->sendError;
            }
            $this->sent[] = $this->current;
        });
        $builder->method('getTransport')->willReturn($transport);

        $timezone = $this->createStub(TimezoneInterface::class);
        $timezone->method('formatDateTime')->willReturnCallback(
            static fn(\DateTimeInterface $dt) => 'LOCAL ' . $dt->format('d.m.Y H:i')
        );

        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('error')->willReturnCallback(function ($message) {
            $this->errors[] = $message;
        });

        return new Mail(
            $builder,
            $this->inline ?? $this->createStub(StateInterface::class),
            $this->createStub(StoreManagerInterface::class),
            $timezone,
            $this->makeConfig($values),
            $logger
        );
    }

    private function request(array $data = []): Request
    {
        return $this->makeRequest($data + [
            'request_id' => 1,
            'store_id' => '2',
            'customer_email' => 'jane@example.test',
            'customer_name' => 'Jane',
            'proof_reference' => 'WDR-1',
            'increment_id' => '000000010',
            'reason' => 'size',
            'requested_at' => '2026-04-01 09:15:00',
            'withdrawal_content' => 'snapshot',
            'status' => 2,
        ]);
    }

    public function testCustomerConfirmationUsesConfiguredTemplateAndVars(): void
    {
        $inline = $this->createMock(StateInterface::class);
        $inline->expects($this->once())->method('suspend');
        $inline->expects($this->once())->method('resume');
        $this->inline = $inline;

        $mail = $this->mail([
            'email/customer_template' => 'cust_tpl',
            'email/sender_identity' => 'sales',
            'compliance/refund_policy_text' => 'Refund in 14 days',
        ]);

        $this->assertTrue($mail->sendCustomerConfirmation($this->request()));

        $sent = $this->sent[0];
        $this->assertSame('cust_tpl', $sent['template']);
        $this->assertSame(['area' => 'frontend', 'store' => 2], $sent['options']);
        $this->assertSame(['sales', 2], $sent['from']);
        $this->assertSame(['jane@example.test', 'Jane'], $sent['to']);
        $data = $sent['vars']['data'];
        $this->assertSame($data, $sent['vars']['withdrawal']);
        $this->assertSame('WDR-1', $data->getData('proof_reference'));
        $this->assertSame('LOCAL 01.04.2026 09:15', $data->getData('requested_at'));
        $this->assertSame('Acknowledged', $data->getData('status_label'));
        $this->assertSame('Refund in 14 days', $data->getData('refund_policy'));
        $this->assertSame('snapshot', $data->getData('withdrawal_content'));
    }

    public function testCustomerConfirmationWithoutEmailIsNotSent(): void
    {
        $this->assertFalse($this->mail()->sendCustomerConfirmation($this->request(['customer_email' => ''])));
        $this->assertSame([], $this->sent);
    }

    public function testAdminNotificationRequiresRecipient(): void
    {
        $this->assertFalse($this->mail()->sendAdminNotification($this->request()));
        $this->assertSame([], $this->sent);

        $this->assertTrue($this->mail(['email/recipient_email' => 'ops@example.test'])->sendAdminNotification($this->request()));
        $this->assertSame('panth_euwithdrawal_email_admin_template', $this->sent[0]['template']);
        $this->assertSame(['ops@example.test', 'Store Administrator'], $this->sent[0]['to']);
    }

    public function testStatusUpdateGoesToCustomer(): void
    {
        $this->assertTrue($this->mail()->sendStatusUpdate($this->request(['status' => 3])));

        $this->assertSame('panth_euwithdrawal_email_status_template', $this->sent[0]['template']);
        $this->assertSame('jane@example.test', $this->sent[0]['to'][0]);
        $this->assertSame('Refunded', $this->sent[0]['vars']['data']->getData('status_label'));
    }

    public function testRefundReminderUsesFixedTemplateAndNeedsRecipient(): void
    {
        $this->assertFalse($this->mail()->sendRefundReminder($this->request()));

        $this->assertTrue($this->mail(['email/recipient_email' => 'ops@example.test'])->sendRefundReminder($this->request()));
        $this->assertSame('panth_euwithdrawal_refund_reminder', $this->sent[0]['template']);
    }

    public function testUnknownStatusAndBadDateFallBack(): void
    {
        $this->mail()->sendStatusUpdate($this->request(['status' => 99, 'requested_at' => 'not a date']));

        $data = $this->sent[0]['vars']['data'];
        $this->assertSame('Received', $data->getData('status_label'));
        $this->assertSame('not a date', $data->getData('requested_at'));
    }

    public function testEmptyRequestedAtStaysEmpty(): void
    {
        $this->mail()->sendStatusUpdate($this->request(['requested_at' => null]));

        $this->assertSame('', $this->sent[0]['vars']['data']->getData('requested_at'));
    }

    public function testTransportFailureIsLoggedAndTranslationResumed(): void
    {
        $inline = $this->createMock(StateInterface::class);
        $inline->expects($this->once())->method('suspend');
        $inline->expects($this->once())->method('resume');
        $this->inline = $inline;
        $this->sendError = new \RuntimeException('smtp refused');

        $this->assertFalse($this->mail()->sendStatusUpdate($this->request()));
        $this->assertStringContainsString('panth_euwithdrawal_email_status_template', $this->errors[0]);
        $this->assertStringContainsString('smtp refused', $this->errors[0]);
    }
}
