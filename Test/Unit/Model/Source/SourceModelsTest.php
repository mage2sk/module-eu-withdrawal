<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model\Source;

use Panth\EuWithdrawal\Model\Source\FloatSide;
use Panth\EuWithdrawal\Model\Source\PeriodBasis;
use Panth\EuWithdrawal\Model\Source\Placement;
use Panth\EuWithdrawal\Model\Source\Status;
use PHPUnit\Framework\TestCase;

class SourceModelsTest extends TestCase
{
    private function values(array $options): array
    {
        return array_column($options, 'value');
    }

    public function testFloatSideOptions(): void
    {
        $this->assertSame(['right', 'left'], $this->values((new FloatSide())->toOptionArray()));
    }

    public function testPeriodBasisOptions(): void
    {
        $this->assertSame(['shipment', 'order'], $this->values((new PeriodBasis())->toOptionArray()));
    }

    public function testPlacementOptions(): void
    {
        $this->assertSame(
            ['floating', 'header', 'footer', 'account'],
            $this->values((new Placement())->toOptionArray())
        );
    }

    public function testStatusLabelsAndStringValues(): void
    {
        $this->assertSame(
            [1 => 'Received', 2 => 'Acknowledged', 3 => 'Refunded', 4 => 'Rejected'],
            Status::getLabels()
        );
        $options = (new Status())->toOptionArray();
        $this->assertSame(['1', '2', '3', '4'], $this->values($options));
        $this->assertSame('Refunded', (string)$options[2]['label']);
    }
}
