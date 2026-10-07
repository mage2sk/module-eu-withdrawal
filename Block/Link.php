<?php
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
        $placement = $this->config->getPlacement();
        if ($slot === 'mobile') {
            return in_array('floating', $placement, true) && !in_array('footer', $placement, true);
        }
        return $slot !== '' && in_array($slot, $placement, true);
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
