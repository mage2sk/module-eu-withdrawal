<?php
/**
 * Copyright (c) Panth Infotech. All rights reserved.
 */
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PeriodBasis implements OptionSourceInterface
{
    public const ORDER = 'order';
    public const SHIPMENT = 'shipment';

    /**
     * @return array<int, array<string, mixed>>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => self::ORDER, 'label' => __('Order date')],
            ['value' => self::SHIPMENT, 'label' => __('Shipment date (date of receipt)')],
        ];
    }
}
