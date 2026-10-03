<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller\Adminhtml;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Redirect;
use Magento\Backend\Model\View\Result\RedirectFactory;
use Magento\Framework\App\Request\Http;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Registry;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Magento\Ui\Component\MassAction\Filter;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\Delete;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\MassDelete;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\Save;
use Panth\EuWithdrawal\Controller\Adminhtml\Request\View;
use Panth\EuWithdrawal\Model\Mail;
use Panth\EuWithdrawal\Model\Request;
use Panth\EuWithdrawal\Model\RequestFactory;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Test\Unit\ConfigStubTrait;
use Panth\EuWithdrawal\Test\Unit\RequestModelTrait;
use PHPUnit\Framework\TestCase;

class RequestActionsTest extends TestCase
{
    use ConfigStubTrait;
    use RequestModelTrait;

    private array $params = [];
    private ?array $redirect = null;
    private array $messages = [];
    /** @var array<int, array> rows the resource "load" can find, keyed by id */
    private array $rows = [];
    private array $saved = [];
    private array $deleted = [];
    private ?\Throwable $saveError = null;
    private ?\Throwable $deleteError = null;
    private ?string $title = null;
    private array $registered = [];

    private function context(): Context
    {
        $request = $this->createStub(Http::class);
        $request->method('getParam')->willReturnCallback(fn($k, $d = null) => $this->params[$k] ?? $d);

        $redirect = $this->createStub(Redirect::class);
        $redirect->method('setPath')->willReturnCallback(function ($path, $params = []) use ($redirect) {
            $this->redirect = [$path, $params];
            return $redirect;
        });
        $redirectFactory = $this->createStub(RedirectFactory::class);
        $redirectFactory->method('create')->willReturn($redirect);

        $messages = $this->createStub(ManagerInterface::class);
        foreach (['Error', 'Success', 'Warning'] as $type) {
            $messages->method('add' . $type . 'Message')->willReturnCallback(
                function ($m) use ($messages, $type) {
                    $this->messages[] = [strtolower($type), (string)$m];
                    return $messages;
                }
            );
        }

        $context = $this->createStub(Context::class);
        $context->method('getRequest')->willReturn($request);
        $context->method('getResultRedirectFactory')->willReturn($redirectFactory);
        $context->method('getMessageManager')->willReturn($messages);
        return $context;
    }

    private function factory(): RequestFactory
    {
        $factory = $this->createStub(RequestFactory::class);
        $factory->method('create')->willReturnCallback(fn() => $this->makeRequest());
        return $factory;
    }

    private function resource(): RequestResource
    {
        $resource = $this->createStub(RequestResource::class);
        $resource->method('load')->willReturnCallback(function (Request $model, $id) use ($resource) {
            if (isset($this->rows[$id])) {
                $model->setData($this->rows[$id]);
            }
            return $resource;
        });
        $resource->method('save')->willReturnCallback(function (Request $model) use ($resource) {
            if ($this->saveError) {
                throw $this->saveError;
            }
            $this->saved[] = $model->getData();
            return $resource;
        });
        $resource->method('delete')->willReturnCallback(function (Request $model) use ($resource) {
            if ($this->deleteError) {
                throw $this->deleteError;
            }
            $this->deleted[] = $model->getId();
            return $resource;
        });
        return $resource;
    }

    private function save(array $flags = [], ?Mail $mail = null): Save
    {
        return new Save(
            $this->context(),
            $this->factory(),
            $this->resource(),
            $this->makeConfig([], $flags),
            $mail ?? $this->createStub(Mail::class)
        );
    }

    public function testSaveWithoutIdGoesToGrid(): void
    {
        $this->save()->execute();

        $this->assertSame(['*/*/index', []], $this->redirect);
        $this->assertSame([], $this->saved);
    }

    public function testSaveOfMissingRequestShowsError(): void
    {
        $this->params = ['request_id' => '5'];
        $this->save()->execute();

        $this->assertSame(['*/*/index', []], $this->redirect);
        $this->assertSame([['error', 'This withdrawal request no longer exists.']], $this->messages);
    }

    public function testSaveUpdatesStatusAndNoteAndNotifiesCustomer(): void
    {
        $this->rows[5] = ['request_id' => 5, 'status' => 1, 'store_id' => 2];
        $this->params = ['request_id' => '5', 'status' => '3', 'admin_note' => '  refunded via bank '];
        $mail = $this->createMock(Mail::class);
        $mail->expects($this->once())->method('sendStatusUpdate')->willReturn(true);

        $this->save(['email/notify_status_change@2' => true], $mail)->execute();

        $this->assertSame(3, $this->saved[0]['status']);
        $this->assertSame('refunded via bank', $this->saved[0]['admin_note']);
        $this->assertSame(['*/*/view', ['request_id' => 5]], $this->redirect);
        $this->assertSame([
            ['success', 'The withdrawal request has been updated.'],
            ['success', 'The customer has been notified of the new status.'],
        ], $this->messages);
    }

    public function testSaveWarnsWhenStatusMailFails(): void
    {
        $this->rows[5] = ['request_id' => 5, 'status' => 1, 'store_id' => 1];
        $this->params = ['request_id' => '5', 'status' => '2'];
        $mail = $this->createStub(Mail::class);
        $mail->method('sendStatusUpdate')->willReturn(false);

        $this->save(['email/notify_status_change' => true], $mail)->execute();

        $this->assertSame(['warning', 'The status update email could not be sent.'], $this->messages[1]);
    }

    public function testSaveIgnoresUnknownStatusAndSkipsMailWhenUnchanged(): void
    {
        $this->rows[5] = ['request_id' => 5, 'status' => 2, 'store_id' => 1];
        $this->params = ['request_id' => '5', 'status' => '99'];
        $mail = $this->createMock(Mail::class);
        $mail->expects($this->never())->method('sendStatusUpdate');

        $this->save(['email/notify_status_change' => true], $mail)->execute();

        $this->assertSame(2, $this->saved[0]['status']);
        $this->assertSame('', $this->saved[0]['admin_note']);
        $this->assertCount(1, $this->messages);
    }

    public function testSaveDoesNotMailWhenNotificationsAreOff(): void
    {
        $this->rows[5] = ['request_id' => 5, 'status' => 1, 'store_id' => 1];
        $this->params = ['request_id' => '5', 'status' => '4'];
        $mail = $this->createMock(Mail::class);
        $mail->expects($this->never())->method('sendStatusUpdate');

        $this->save([], $mail)->execute();

        $this->assertSame(4, $this->saved[0]['status']);
    }

    public function testSaveErrorIsReported(): void
    {
        $this->rows[5] = ['request_id' => 5, 'status' => 1];
        $this->params = ['request_id' => '5', 'status' => '2'];
        $this->saveError = new \RuntimeException('constraint');

        $this->save()->execute();

        $this->assertSame([['error', 'Could not update the request: constraint']], $this->messages);
        $this->assertSame(['*/*/view', ['request_id' => 5]], $this->redirect);
    }

    public function testDeleteFlow(): void
    {
        $delete = new Delete($this->context(), $this->factory(), $this->resource());

        $delete->execute();
        $this->assertSame([], $this->messages, 'no id just returns to the grid');

        $this->params = ['request_id' => '8'];
        $delete->execute();
        $this->assertSame(['error', 'This withdrawal request no longer exists.'], $this->messages[0]);

        $this->rows[8] = ['request_id' => 8];
        $delete->execute();
        $this->assertSame([8], $this->deleted);
        $this->assertSame(['success', 'The withdrawal request has been deleted.'], $this->messages[1]);

        $this->deleteError = new \RuntimeException('fk');
        $delete->execute();
        $this->assertSame(['error', 'Could not delete the request: fk'], $this->messages[2]);
        $this->assertSame(['*/*/index', []], $this->redirect);
    }

    public function testMassDeleteCountsDeletedRows(): void
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('getIterator')->willReturn(new \ArrayIterator([
            $this->makeRequest(['request_id' => 1]),
            $this->makeRequest(['request_id' => 2]),
        ]));
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($collection);
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willReturnArgument(0);

        (new MassDelete($this->context(), $filter, $collectionFactory, $this->resource()))->execute();

        $this->assertSame([1, 2], $this->deleted);
        $this->assertSame([['success', 'A total of 2 record(s) have been deleted.']], $this->messages);
        $this->assertSame(['*/*/index', []], $this->redirect);
    }

    public function testMassDeleteReportsFilterErrors(): void
    {
        $collectionFactory = $this->createStub(CollectionFactory::class);
        $collectionFactory->method('create')->willReturn($this->createStub(Collection::class));
        $filter = $this->createStub(Filter::class);
        $filter->method('getCollection')->willThrowException(new \RuntimeException('bad selection'));

        (new MassDelete($this->context(), $filter, $collectionFactory, $this->resource()))->execute();

        $this->assertSame([['error', 'Could not delete records: bad selection']], $this->messages);
    }

    private function view(): View
    {
        $title = $this->createStub(Title::class);
        $title->method('prepend')->willReturnCallback(function ($t) {
            $this->title = (string)$t;
        });
        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);
        $page = $this->createStub(Page::class);
        $page->method('getConfig')->willReturn($config);
        $pageFactory = $this->createStub(PageFactory::class);
        $pageFactory->method('create')->willReturn($page);

        $registry = $this->createStub(Registry::class);
        $registry->method('register')->willReturnCallback(function ($key, $value) {
            $this->registered[$key] = $value;
        });

        return new View($this->context(), $pageFactory, $this->factory(), $this->resource(), $registry);
    }

    public function testViewRegistersRequestAndSetsTitle(): void
    {
        $this->rows[3] = ['request_id' => 3, 'proof_reference' => 'WDR-3'];
        $this->params = ['request_id' => '3'];

        $result = $this->view()->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame('Withdrawal Request #WDR-3', $this->title);
        $this->assertSame(3, $this->registered[View::REGISTRY_KEY]->getId());
    }

    public function testViewOfUnknownRequestRedirects(): void
    {
        $this->params = ['request_id' => '404'];
        $this->view()->execute();

        $this->assertSame(['*/*/index', []], $this->redirect);
        $this->assertSame([['error', 'This withdrawal request no longer exists.']], $this->messages);
        $this->assertSame([], $this->registered);
    }
}
