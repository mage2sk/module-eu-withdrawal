<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class PeriodBasis implements OptionSourceInterface
{
    public const ORDER = 'order';
    public const SHIPMENT = 'shipment';

    public function toOptionArray(): array
    {
        return [
            [
                'value' => self::SHIPMENT,
                'label' => __('Shipment date of the last parcel (closest record of the date of receipt)'),
            ],
            ['value' => self::ORDER, 'label' => __('Order date (shorter than the legal minimum - not recommended)')],
        ];
    }
}
