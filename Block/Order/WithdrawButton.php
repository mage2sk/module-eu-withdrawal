<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Renders a "Cancel my order" button on the customer order-view page, linking
 * to a signed, pre-filled withdrawal page. Shows only for an eligible order
 * (module enabled, within the window, and not already withdrawn).
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Order;

use Magento\Framework\Registry;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Sales\Api\Data\OrderInterface;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\TokenManager;
use Panth\EuWithdrawal\Model\WithdrawalService;

class WithdrawButton extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly Config $config,
        private readonly WithdrawalService $service,
        private readonly TokenManager $tokenManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    private function getOrder(): ?OrderInterface
    {
        $order = $this->registry->registry('current_order');
        return $order instanceof OrderInterface ? $order : null;
    }

    public function isEligible(): bool
    {
        $order = $this->getOrder();
        if (!$order || !$order->getIncrementId()) {
            return false;
        }
        $storeId = (int)$order->getStoreId();
        if (!$this->config->isEnabled($storeId)) {
            return false;
        }
        try {
            return $this->service->isWithinWindow($order) && !$this->service->hasExistingRequest($order);
        } catch (\Throwable $e) {
            return false;
        }
    }

    public function getLabel(): string
    {
        return $this->config->getButtonLabel();
    }

    public function getWithdrawUrl(): string
    {
        $order = $this->getOrder();
        if (!$order) {
            return $this->getUrl('withdrawal');
        }
        $increment = (string)$order->getIncrementId();
        $email = (string)$order->getCustomerEmail();
        return $this->getUrl('withdrawal', [
            '_query' => [
                'o' => $increment,
                'e' => $email,
                't' => $this->tokenManager->generate($increment, $email),
            ],
        ]);
    }

    protected function _toHtml(): string
    {
        return $this->isEligible() ? parent::_toHtml() : '';
    }
}
