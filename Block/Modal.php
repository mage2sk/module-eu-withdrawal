<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Site-wide withdrawal modal container. Renders only when the module is enabled
 * and at least one storefront trigger placement is active.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\EuWithdrawal\Model\Config;

class Modal extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->config->isEnabled() || $this->config->getPlacement() === []) {
            return '';
        }
        return parent::_toHtml();
    }
}
