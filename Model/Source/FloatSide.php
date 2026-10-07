<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class FloatSide implements OptionSourceInterface
{
    public const RIGHT = 'right';
    public const LEFT = 'left';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::RIGHT, 'label' => __('Right')],
            ['value' => self::LEFT, 'label' => __('Left')],
        ];
    }
}
