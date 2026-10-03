<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\EuWithdrawal\Cron\ProcessRequests;
use Panth\EuWithdrawal\Model\Mail;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\Status;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ProcessRequestsTest extends TestCase
{
    use ConfigStubTrait;
    use RequestModelTrait;

    private array $collections = [];
    private array $filters = [];
    private array $pageSizes = [];
    private array $saved = [];
    private array $warnings = [];

    private function collection(array $items): Collection
    {
        $index = count($this->collections);
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(
            function ($field, $cond) use ($collection, $index) {
                $this->filters[$index][$field] = $cond;
                return $collection;
            }
        );
        $collection->method('setOrder')->willReturnSelf();
        $collection->method('setCurPage')->willReturnSelf();
        $collection->method('setPageSize')->willReturnCallback(function ($size) use ($collection, $index) {
            $this->pageSizes[$index] = $size;
            return $collection;
        });
        $collection->method('getIterator')->willReturn(new \ArrayIterator($items));
        $this->collections[] = $collection;
        return $collection;
    }

    private function cron(array $values, array $flags, Mail $mail, ?CollectionFactory $factory = null): ProcessRequests
    {
        if ($factory === null) {
            $factory = $this->createStub(CollectionFactory::class);
            $factory->method('create')->willReturnOnConsecutiveCalls(...$this->collections);
        }
        $resource = $this->createStub(RequestResource::class);
        $resource->method('save')->willReturnCallback(function ($request) use ($resource) {
            if ($request->getData('explode')) {
                throw new \RuntimeException('save failed');
            }
            $this->saved[] = $request->getData();
            return $resource;
        });
        $logger = $this->createStub(LoggerInterface::class);
        $logger->method('warning')->willReturnCallback(function ($m) {
            $this->warnings[] = $m;
        });

        return new ProcessRequests(
            $this->makeConfig($values, $flags),
            $mail,
            $factory,
            $resource,
            $this->createStub(TimezoneInterface::class),
            $logger
        );
    }

    public function testDisabledCronDoesNothing(): void
    {
        $factory = $this->createMock(CollectionFactory::class);
        $factory->expects($this->never())->method('create');

        $this->cron([], [], $this->createStub(Mail::class), $factory)->execute();
    }

    public function testRetriesConfirmationsOnlyForStoresThatSendThem(): void
    {
        $this->collection([
            $this->makeRequest(['request_id' => 1, 'store_id' => 1]),
            $this->makeRequest(['request_id' => 2, 'store_id' => 2]),
            $this->makeRequest(['request_id' => 3, 'store_id' => 1, 'fail' => true]),
        ]);
        $mail = $this->createStub(Mail::class);
        $mail->method('sendCustomerConfirmation')->willReturnCallback(fn($r) => !$r->getData('fail'));

        $this->cron(
            ['batch/batch_size' => '20'],
            ['batch/cron_enabled' => true, 'email/send_customer_confirmation@1' => true],
            $mail
        )->execute();

        $this->assertSame([1], array_column($this->saved, 'request_id'));
        $this->assertSame(1, $this->saved[0]['confirmation_sent']);
        $this->assertSame(0, $this->filters[0]['confirmation_sent']);
        $this->assertSame(['nin' => [Status::REJECTED]], $this->filters[0]['status']);
        $this->assertSame(20, $this->pageSizes[0]);
        $this->assertCount(1, $this->collections, 'reminders are disabled, so no second collection');
    }

    public function testConfirmationErrorsAreLoggedAndLoopContinues(): void
    {
        $this->collection([
            $this->makeRequest(['request_id' => 1, 'store_id' => 1, 'explode' => true]),
            $this->makeRequest(['request_id' => 2, 'store_id' => 1]),
        ]);
        $mail = $this->createStub(Mail::class);
        $mail->method('sendCustomerConfirmation')->willReturn(true);

        $this->cron([], ['batch/cron_enabled' => true, 'email/send_customer_confirmation' => true], $mail)->execute();

        $this->assertSame([2], array_column($this->saved, 'request_id'));
        $this->assertStringContainsString('retry confirmation failed: save failed', $this->warnings[0]);
    }

    public function testRefundRemindersAreSentForOldOpenRequests(): void
    {
        $this->collection([]);
        $this->collection([
            $this->makeRequest(['request_id' => 7, 'store_id' => 1]),
            $this->makeRequest(['request_id' => 8, 'store_id' => 1, 'skip' => true]),
            $this->makeRequest(['request_id' => 9, 'store_id' => 1, 'explode' => true]),
        ]);
        $mail = $this->createStub(Mail::class);
        $mail->method('sendRefundReminder')->willReturnCallback(fn($r) => !$r->getData('skip'));

        $before = time();
        $this->cron(
            ['batch/refund_reminder_days' => '5'],
            ['batch/cron_enabled' => true, 'batch/refund_reminder_enabled' => true],
            $mail
        )->execute();

        $this->assertSame([7], array_column($this->saved, 'request_id'));
        $this->assertSame(1, $this->saved[0]['reminder_sent']);
        $this->assertStringContainsString('refund reminder failed', $this->warnings[0]);

        $filters = $this->filters[1];
        $this->assertSame(0, $filters['reminder_sent']);
        $this->assertSame(['in' => [Status::RECEIVED, Status::ACKNOWLEDGED]], $filters['status']);
        $threshold = strtotime($filters['requested_at']['lteq'] . ' UTC');
        $this->assertEqualsWithDelta($before - 5 * 86400, $threshold, 5);
        $this->assertSame(50, $this->pageSizes[1]);
    }
}
