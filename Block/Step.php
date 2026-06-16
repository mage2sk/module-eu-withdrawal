<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 *
 * Theme-aware step block. Each layout handle (form / confirm / success) passes
 * a hyva_template + luma_template pair; the active theme decides which renders,
 * using the shared Panth\Core\Helper\Theme detector — same approach as
 * Panth_DynamicForms.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Block;

use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Panth\Core\Helper\Theme;

class Step extends Template
{
    public function __construct(
        Context $context,
        private readonly Theme $themeHelper,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    protected function _toHtml(): string
    {
        if (!$this->getTemplate()) {
            $hyva = (string)$this->getData('hyva_template');
            $luma = (string)$this->getData('luma_template');
            $this->setTemplate($this->themeHelper->isHyva() && $hyva !== '' ? $hyva : $luma);
        }
        return parent::_toHtml();
    }
}
