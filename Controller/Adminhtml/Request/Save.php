<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Adminhtml\Request;

use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\Mail;
use Panth\EuWithdrawal\Model\RequestFactory;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\Source\Status;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_EuWithdrawal::request_manage';

    public function __construct(
        Context $context,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly Config $config,
        private readonly Mail $mail
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

        $model = $this->requestFactory->create();
        $this->requestResource->load($model, $id);
        if (!$model->getId()) {
            $this->messageManager->addErrorMessage(__('This withdrawal request no longer exists.'));
            return $redirect->setPath('*/*/index');
        }

        $previousStatus = (int)$model->getData('status');
        $status = (int)$this->getRequest()->getParam('status');
        if (array_key_exists($status, Status::getLabels())) {
            $model->setData('status', $status);
        }
        $model->setData('admin_note', trim((string)$this->getRequest()->getParam('admin_note')));

        try {
            $this->requestResource->save($model);
            $this->messageManager->addSuccessMessage(__('The withdrawal request has been updated.'));
            if ((int)$model->getData('status') !== $previousStatus
                && $this->config->notifyStatusChange((int)$model->getData('store_id'))
            ) {
                if ($this->mail->sendStatusUpdate($model)) {
                    $this->messageManager->addSuccessMessage(__('The customer has been notified of the new status.'));
                } else {
                    $this->messageManager->addWarningMessage(__('The status update email could not be sent.'));
                }
            }
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage(__('Could not update the request: %1', $e->getMessage()));
        }

        return $redirect->setPath('*/*/view', ['request_id' => $id]);
    }
}
