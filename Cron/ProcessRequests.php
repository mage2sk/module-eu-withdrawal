<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Batch processor for withdrawal requests. Each run handles up to a configured
 * batch size and does two jobs:
 *   1. Retries confirmation emails that failed to send (the directive requires
 *      the consumer to actually receive durable proof).
 *   2. Sends the admin a refund-deadline reminder before the 14-day refund
 *      window lapses, for requests that are still unresolved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Cron;

use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\Mail;
use Panth\EuWithdrawal\Model\ResourceModel\Request as RequestResource;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\Status;
use Psr\Log\LoggerInterface;

class ProcessRequests
{
    public function __construct(
        private readonly Config $config,
        private readonly Mail $mail,
        private readonly CollectionFactory $collectionFactory,
        private readonly RequestResource $requestResource,
        private readonly TimezoneInterface $timezone,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isCronEnabled()) {
            return;
        }

        $batchSize = $this->config->getBatchSize();
        $this->retryConfirmations($batchSize);
        $this->sendRefundReminders($batchSize);
    }

    private function retryConfirmations(int $batchSize): void
    {
        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('confirmation_sent', 0)
            ->addFieldToFilter('status', ['nin' => [Status::REJECTED]])
            ->setOrder('request_id', 'ASC')
            ->setPageSize($batchSize)
            ->setCurPage(1);

        foreach ($collection as $request) {
            $storeId = (int)$request->getStoreId();
            if (!$this->config->sendCustomerConfirmation($storeId)) {
                continue;
            }
            try {
                if ($this->mail->sendCustomerConfirmation($request)) {
                    $request->setData('confirmation_sent', 1);
                    $this->requestResource->save($request);
                }
            } catch (\Throwable $e) {
                $this->logger->warning('[Panth EuWithdrawal] retry confirmation failed: ' . $e->getMessage());
            }
        }
    }

    private function sendRefundReminders(int $batchSize): void
    {
        if (!$this->config->isRefundReminderEnabled()) {
            return;
        }

        $days = $this->config->getRefundReminderDays();
        $threshold = $this->timezone->date()->modify('-' . $days . ' days')->format('Y-m-d H:i:s');

        $collection = $this->collectionFactory->create();
        $collection->addFieldToFilter('reminder_sent', 0)
            ->addFieldToFilter('status', ['in' => [Status::RECEIVED, Status::ACKNOWLEDGED]])
            ->addFieldToFilter('requested_at', ['lteq' => $threshold])
            ->setOrder('request_id', 'ASC')
            ->setPageSize($batchSize)
            ->setCurPage(1);

        foreach ($collection as $request) {
            try {
                $this->mail->sendRefundReminder($request);
                // Mark as reminded regardless of recipient config to avoid re-querying forever.
                $request->setData('reminder_sent', 1);
                $this->requestResource->save($request);
            } catch (\Throwable $e) {
                $this->logger->warning('[Panth EuWithdrawal] refund reminder failed: ' . $e->getMessage());
            }
        }
    }
}
