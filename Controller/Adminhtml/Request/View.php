<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\Registry;
use Magento\Framework\View\Result\Page;
use Magento\Framework\View\Result\PageFactory;
use Panth\EuWithdrawal\Model\Request as RequestModel;
use Panth\EuWithdrawal\Model\RequestFactory;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

class View extends Action
{
    public const ADMIN_RESOURCE = 'Panth_EuWithdrawal::request_view';
    public const REGISTRY_KEY = 'panth_euwithdrawal_request';

    public function __construct(
        Context $context,
        private readonly PageFactory $resultPageFactory,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('request_id');
        /** @var RequestModel $model */
        $model = $this->requestFactory->create();
        if ($id) {
            $this->requestResource->load($model, $id);
        }
        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('This withdrawal request no longer exists.'));
            return $this->resultRedirectFactory->create()->setPath('*/*/index');
        }

        $this->registry->register(self::REGISTRY_KEY, $model);

        /** @var Page $resultPage */
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('Panth_EuWithdrawal::requests');
        $resultPage->getConfig()->getTitle()->prepend(
            __('Withdrawal Request #%1', $model->getProofReference())
        );
        return $resultPage;
    }
}
