<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * "My withdrawals" link in the customer account navigation (Hyva + Luma).
 * Rendered only when the module is enabled and the "Customer Account"
 * placement is selected.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block\Account;

use Magento\Customer\Block\Account\SortLink;
use Magento\Framework\App\DefaultPathInterface;
use Magento\Framework\View\Element\Template\Context;
use Panth\EuWithdrawal\Model\Config;
use Panth\EuWithdrawal\Model\Source\Placement;

class NavLink extends SortLink
{
    public function __construct(
        Context $context,
        DefaultPathInterface $defaultPath,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $defaultPath, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled() || !in_array(Placement::ACCOUNT, $this->config->getPlacement(), true)) {
            return '';
        }
        return parent::_toHtml();
    }
}
