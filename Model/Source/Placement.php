<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Placement implements OptionSourceInterface
{
    public const FLOATING = 'floating';
    public const HEADER = 'header';
    public const FOOTER = 'footer';
    public const ACCOUNT = 'account';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::FLOATING, 'label' => __('Floating side button (recommended)')],
            ['value' => self::HEADER, 'label' => __('Header link')],
            ['value' => self::FOOTER, 'label' => __('Footer link')],
            ['value' => self::ACCOUNT, 'label' => __('Customer Account')],
        ];
    }
}
