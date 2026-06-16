<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Returns the logged-in customer's eligible (within-window, not cancelled)
 * orders as JSON so the storefront can offer an order-number dropdown.
 *
 * Session-based and never cached, so it is safe alongside Full Page Cache:
 * the form markup stays generic/cacheable and this endpoint personalises it.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Controller\Customer;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Stdlib\DateTime\TimezoneInterface;
use Magento\Sales\Model\Order;
use Magento\Sales\Model\ResourceModel\Order\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Panth\EuWithdrawal\Model\Config;

class Orders implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->config->isEnabled() || !$this->customerSession->isLoggedIn()) {
            return $result->setData(['loggedIn' => false, 'orders' => []]);
        }

        $customer = $this->customerSession->getCustomer();
        $storeId = (int)$this->storeManager->getStore()->getId();
        $thresholdDays = $this->config->getPeriodDays($storeId);
        $threshold = gmdate('Y-m-d H:i:s', time() - ($thresholdDays * 86400));

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToSelect(['increment_id', 'created_at', 'grand_total', 'order_currency_code'])
            ->addFieldToFilter('customer_id', (int)$customer->getId())
            ->addFieldToFilter('state', ['nin' => [Order::STATE_CANCELED, Order::STATE_CLOSED]])
            ->addFieldToFilter('created_at', ['gteq' => $threshold])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(25);

        $orders = [];
        foreach ($collection as $order) {
            $orders[] = [
                'id' => (string)$order->getIncrementId(),
                'label' => sprintf(
                    '#%s — %s — %s %s',
                    $order->getIncrementId(),
                    $this->formatDate((string)$order->getCreatedAt()),
                    number_format((float)$order->getGrandTotal(), 2),
                    (string)$order->getOrderCurrencyCode()
                ),
            ];
        }

        return $result->setData([
            'loggedIn' => true,
            'email' => (string)$customer->getEmail(),
            'name' => trim($customer->getFirstname() . ' ' . $customer->getLastname()),
            'orders' => $orders,
        ]);
    }

    private function formatDate(string $utc): string
    {
        if ($utc === '') {
            return '';
        }
        try {
            $dt = new \DateTime($utc, new \DateTimeZone('UTC'));
        } catch (\Throwable $e) {
            return $utc;
        }
        return $this->timezone->formatDateTime($dt, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
    }
}
