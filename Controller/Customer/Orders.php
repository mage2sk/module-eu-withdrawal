<?php
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
use Panth\EuWithdrawal\Model\Source\PeriodBasis;
use Panth\EuWithdrawal\Model\WithdrawalService;

class Orders implements HttpGetActionInterface
{
    public function __construct(
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly CollectionFactory $orderCollectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly TimezoneInterface $timezone,
        private readonly Config $config,
        private readonly WithdrawalService $service
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
        $thresholdDays = $this->config->getPeriodDays($storeId) + 31;
        $threshold = gmdate('Y-m-d H:i:s', time() - ($thresholdDays * 86400));

        $collection = $this->orderCollectionFactory->create();
        $collection->addFieldToSelect([
            'entity_id', 'increment_id', 'created_at', 'grand_total', 'order_currency_code', 'state', 'store_id',
        ])
            ->addFieldToFilter('customer_id', (int)$customer->getId())
            ->addFieldToFilter('state', ['nin' => [Order::STATE_CANCELED, Order::STATE_CLOSED]])
            ->setOrder('created_at', 'DESC')
            ->setPageSize(25);
        if ($this->config->getPeriodBasis($storeId) !== PeriodBasis::SHIPMENT) {
            $collection->addFieldToFilter('created_at', ['gteq' => $threshold]);
        }

        $withRequest = $this->service->getOrderIdsWithRequest($collection->getColumnValues('entity_id'));
        $orders = [];
        foreach ($collection as $order) {
            if (isset($withRequest[(int)$order->getEntityId()]) || !$this->service->isOrderEligible($order)) {
                continue;
            }
            $orders[] = [
                'id' => (string)$order->getIncrementId(),
                'label' => sprintf(
                    '#%s - %s - %s %s',
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
