<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Customer-account "My withdrawals" page (login required). Lists the logged-in
 * customer's withdrawal requests with their current status.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\Config;

class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession,
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

        /** @var Page $page */
        $page = $this->pageFactory->create();
        $page->addHandle('customer_account');
        $page->getConfig()->getTitle()->set(__('My withdrawals'));
        return $page;
    }
}
