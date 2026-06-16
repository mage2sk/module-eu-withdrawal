<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Panth\EuWithdrawal\Model\RequestFactory;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;

class Delete extends Action
{
    public const ADMIN_RESOURCE = 'Panth_EuWithdrawal::request_manage';

    public function __construct(
        Context $context,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $id = (int)$this->getRequest()->getParam('request_id');
        if (!$id) {
            return $redirect->setPath('*/*/index');
        }

        try {
            $model = $this->requestFactory->create();
            $this->requestResource->load($model, $id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This withdrawal request no longer exists.'));
                return $redirect->setPath('*/*/index');
            }
            $this->requestResource->delete($model);
            $this->messageManager->addSuccessMessage(__('The withdrawal request has been deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not delete the request: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/index');
    }
}
