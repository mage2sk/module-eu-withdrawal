<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Always-visible storefront withdrawal link (header / footer). Renders only
 * when the module is enabled and the slot is included in the placement config,
 * so the legally-required button stays accessible throughout the period.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\EuWithdrawal\Model\Config;

class Link extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getSlot(): string
    {
        return (string)$this->getData('slot');
    }

    public function canShow(): bool
    {
        if (!$this->config->isEnabled()) {
            return false;
        }
        $slot = $this->getSlot();
        return $slot !== '' && in_array($slot, $this->config->getPlacement(), true);
    }

    public function getLabel(): string
    {
        return $this->config->getButtonLabel();
    }

    public function getFloatSide(): string
    {
        return $this->config->getFloatSide();
    }

    public function getHref(): string
    {
        return $this->getUrl('withdrawal');
    }

    protected function _toHtml(): string
    {
        return $this->canShow() ? parent::_toHtml() : '';
    }
}
