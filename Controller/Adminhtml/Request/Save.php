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
use Panth\EuWithdrawal\Model\StatusChangeValidator;
use Panth\EuWithdrawal\Model\Source\Status;

class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Panth_EuWithdrawal::request_manage';

    public function __construct(
        Context $context,
        private readonly RequestFactory $requestFactory,
        private readonly RequestResource $requestResource,
        private readonly Config $config,
        private readonly Mail $mail,
        private readonly StatusChangeValidator $statusValidator
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
        $comment = trim((string)$this->getRequest()->getParam('status_comment'));
        $note = trim((string)$this->getRequest()->getParam('admin_note'));
        if (array_key_exists($status, Status::getLabels())) {
            $error = $this->statusValidator->validate($previousStatus, $status, $comment);
            if ($error !== null) {
                $this->messageManager->addErrorMessage($error);
                return $redirect->setPath('*/*/view', ['request_id' => $id]);
            }
            $model->setData('status', $status);
        }
        if ($comment !== '') {
            $label = Status::getLabels()[(int)$model->getData('status')] ?? 'Received';
            $line = (string)__('%1 - %2: %3', gmdate('Y-m-d H:i') . ' UTC', __($label), $comment);
            $note = $note === '' ? $line : $note . PHP_EOL . $line;
        }
        $model->setData('admin_note', $note);

        try {
            $this->requestResource->save($model);
            $this->messageManager->addSuccessMessage(__('The withdrawal request has been updated.'));
            if ((int)$model->getData('status') !== $previousStatus
                && $this->config->notifyStatusChange((int)$model->getData('store_id'))
            ) {
                if ($this->mail->sendStatusUpdate($model, $comment)) {
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
