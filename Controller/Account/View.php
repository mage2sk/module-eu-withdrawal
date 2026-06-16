<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * View a single withdrawal request from the customer account (login + ownership
 * required). Reuses the status view via the request-scoped context.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\RequestFactory;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\WithdrawalContext;

class View implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly RequestInterface $request,
        private readonly CustomerSession $customerSession,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly WithdrawalContext $context,
        private readonly ManagerInterface $messageManager,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        if (!$this->config->isEnabled()) {
            return $this->redirectFactory->create()->setPath('noroute');
        }
        if (!$this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('customer/account/login');
        }

        $id = (int)$this->request->getParam('request_id');
        $model = $this->requestFactory->create();
        if ($id) {
            $this->requestResource->load($model, $id);
        }

        // Ownership check — a customer may only view their own requests.
        $email = strtolower((string)$this->customerSession->getCustomer()->getEmail());
        if (!$model->getId() || strtolower((string)$model->getCustomerEmail()) !== $email) {
            $this->messageManager->addErrorMessage(__('That withdrawal request could not be found.'));
            return $this->redirectFactory->create()->setPath('withdrawal/account');
        }

        $this->context->setStatusRequest($model);

        /** @var Page $page */
        $page = $this->pageFactory->create();
        $page->addHandle('customer_account');
        $page->getConfig()->getTitle()->set(__('Withdrawal %1', $model->getProofReference()));
        return $page;
    }
}
