<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FloatSide implements OptionSourceInterface
{
    public const RIGHT = 'right';
    public const LEFT = 'left';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::RIGHT, 'label' => __('Right')],
            ['value' => self::LEFT, 'label' => __('Left')],
        ];
    }
}
