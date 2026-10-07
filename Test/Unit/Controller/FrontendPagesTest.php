<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Controller;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Controller\Result\Forward;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\View\Result\Page;
use Panth\EuWithdrawal\Controller\Account\Index as AccountIndex;
use Panth\EuWithdrawal\Controller\Account\View as AccountView;
use Panth\EuWithdrawal\Controller\Index\Index as FormIndex;
use Panth\EuWithdrawal\Controller\Index\Success;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\WithdrawalContext;

class FrontendPagesTest extends ControllerTestCase
{
    private array $flags = ['general/enabled' => true];
    private bool $loggedIn = true;
    private array $collectionCalls = [];

    private function session(): CustomerSession
    {
        $session = $this->createStub(CustomerSession::class);
        $session->method('isLoggedIn')->willReturnCallback(fn() => $this->loggedIn);
        $session->method('getCustomerId')->willReturn(15);
        return $session;
    }

    public function testFormIndexForwardsToNoRouteWhenDisabled(): void
    {
        $forwarded = [];
        $forward = $this->createStub(Forward::class);
        $forward->method('forward')->willReturnCallback(function ($action) use (&$forwarded, $forward) {
            $forwarded[] = $action;
            return $forward;
        });
        $forwardFactory = $this->createStub(ForwardFactory::class);
        $forwardFactory->method('create')->willReturn($forward);

        $result = (new FormIndex($this->pageFactory(), $forwardFactory, $this->makeConfig()))->execute();

        $this->assertSame($forward, $result);
        $this->assertSame(['noroute'], $forwarded);
    }

    public function testFormIndexUsesButtonLabelAsTitle(): void
    {
        $forwardFactory = $this->createStub(ForwardFactory::class);
        $config = $this->makeConfig(['general/button_label' => 'Withdraw now'], $this->flags);

        $result = (new FormIndex($this->pageFactory(), $forwardFactory, $config))->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame('Withdraw now', $this->title);
    }

    public function testSuccessWithoutProofRedirectsBackAndClearsPersistor(): void
    {
        $this->persisted['panth_euwithdrawal_proof'] = ['proof_reference' => ''];
        $context = new WithdrawalContext();
        $controller = new Success(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->persistor(),
            $this->makeConfig([], $this->flags),
            $context
        );

        $controller->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
        $this->assertSame([], $this->persisted);
        $this->assertSame('', $context->getProofReference());
    }

    public function testSuccessIsSingleUseAndFillsContext(): void
    {
        $this->persisted['panth_euwithdrawal_proof'] = [
            'proof_reference' => 'WDR-1',
            'increment_id' => '000000001',
            'email' => 'a@example.test',
        ];
        $context = new WithdrawalContext();
        $controller = new Success(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->persistor(),
            $this->makeConfig([], $this->flags),
            $context
        );

        $this->assertInstanceOf(Page::class, $controller->execute());
        $this->assertSame('Withdrawal received', $this->title);
        $this->assertSame('WDR-1', $context->getProofReference());
        $this->assertSame('000000001', $context->getProofIncrementId());
        $this->assertSame('a@example.test', $context->getProofEmail());

        $controller->execute();
        $this->assertSame('withdrawal', $this->redirectPath);
    }

    public function testSuccessRedirectsWhenModuleDisabled(): void
    {
        $this->persisted['panth_euwithdrawal_proof'] = ['proof_reference' => 'WDR-1'];
        $controller = new Success(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->persistor(),
            $this->makeConfig(),
            new WithdrawalContext()
        );

        $controller->execute();

        $this->assertSame('withdrawal', $this->redirectPath);
    }

    public function testAccountIndexGuards(): void
    {
        $controller = new AccountIndex($this->pageFactory(), $this->redirectFactory(), $this->session(), $this->makeConfig());
        $controller->execute();
        $this->assertSame('noroute', $this->redirectPath);

        $this->loggedIn = false;
        $controller = new AccountIndex(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->session(),
            $this->makeConfig([], $this->flags)
        );
        $controller->execute();
        $this->assertSame('customer/account/login', $this->redirectPath);
    }

    public function testAccountIndexRendersInsideCustomerAccount(): void
    {
        $controller = new AccountIndex(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->session(),
            $this->makeConfig([], $this->flags)
        );

        $this->assertInstanceOf(Page::class, $controller->execute());
        $this->assertSame(['customer_account'], $this->handles);
        $this->assertSame('My withdrawals', $this->title);
    }

    private function accountView(array $items, array $flags): AccountView
    {
        $collection = $this->createStub(Collection::class);
        $collection->method('addFieldToFilter')->willReturnCallback(function ($f, $v) use ($collection) {
            $this->collectionCalls[] = ['filter', $f, $v];
            return $collection;
        });
        $collection->method('addCustomerFilter')->willReturnCallback(function ($id) use ($collection) {
            $this->collectionCalls[] = ['customer', $id];
            return $collection;
        });
        $collection->method('setPageSize')->willReturnSelf();
        $collection->method('getFirstItem')->willReturn($items[0] ?? $this->makeRequest());
        $factory = $this->createStub(CollectionFactory::class);
        $factory->method('create')->willReturn($collection);

        return new AccountView(
            $this->pageFactory(),
            $this->redirectFactory(),
            $this->httpRequest(),
            $this->session(),
            $factory,
            $this->context,
            $this->messageManager(),
            $this->makeConfig([], $flags)
        );
    }

    private WithdrawalContext $context;

    protected function setUp(): void
    {
        parent::setUp();
        $this->context = new WithdrawalContext();
    }

    public function testAccountViewShowsOwnRequest(): void
    {
        $request = $this->makeRequest(['request_id' => 9, 'proof_reference' => 'WDR-9']);
        $this->params['request_id'] = '9';

        $result = $this->accountView([$request], $this->flags)->execute();

        $this->assertInstanceOf(Page::class, $result);
        $this->assertSame([['filter', 'main_table.request_id', 9], ['customer', 15]], $this->collectionCalls);
        $this->assertSame($request, $this->context->getStatusRequest());
        $this->assertSame('Withdrawal WDR-9', $this->title);
        $this->assertSame(['customer_account'], $this->handles);
    }

    public function testAccountViewRejectsMissingOrForeignRequest(): void
    {
        $this->params['request_id'] = '9';
        $this->accountView([], $this->flags)->execute();

        $this->assertSame('withdrawal/account', $this->redirectPath);
        $this->assertSame(['error', 'That withdrawal request could not be found.'], $this->lastMessage());
        $this->assertNull($this->context->getStatusRequest());
    }

    public function testAccountViewWithoutIdDoesNotQuery(): void
    {
        $this->accountView([], $this->flags)->execute();

        $this->assertSame([], $this->collectionCalls);
        $this->assertSame('withdrawal/account', $this->redirectPath);
    }

    public function testAccountViewGuards(): void
    {
        $this->accountView([], [])->execute();
        $this->assertSame('noroute', $this->redirectPath);

        $this->loggedIn = false;
        $this->accountView([], $this->flags)->execute();
        $this->assertSame('customer/account/login', $this->redirectPath);
    }
}
