<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Lists the logged-in customer's withdrawal requests for the account page.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Account;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\ResourceModel\Request\Collection;
use Panth\EuWithdrawal\Model\ResourceModel\Request\CollectionFactory;
use Panth\EuWithdrawal\Model\Source\Status;

class Withdrawals extends Template
{
    private ?Collection $requests = null;

    public function __construct(
        Context $context,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $collectionFactory,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    /**
     * @return Collection|\Panth\EuWithdrawal\Model\Request[]
     */
    public function getRequests()
    {
        if ($this->requests === null) {
            $email = (string)$this->customerSession->getCustomer()->getEmail();
            $collection = $this->collectionFactory->create();
            $collection->addFieldToFilter('customer_email', $email)
                ->setOrder('request_id', 'DESC');
            $this->requests = $collection;
        }
        return $this->requests;
    }

    public function getStatusLabel(int $status): string
    {
        return (string)__(Status::getLabels()[$status] ?? 'Received');
    }

    public function formatRequestedAt(?string $utc): string
    {
        if (!$utc) {
            return '';
        }
        try {
            $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return (string)$utc;
        }
        return $this->timezone->formatDateTime($dt, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
    }

    public function getNewWithdrawalUrl(): string
    {
        return $this->getUrl('withdrawal');
    }

    public function getViewUrl(int $requestId): string
    {
        return $this->getUrl('withdrawal/account/view', ['request_id' => $requestId]);
    }

    public function getButtonLabel(): string
    {
        return $this->config->getButtonLabel();
    }
}
