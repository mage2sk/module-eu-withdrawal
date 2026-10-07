<?php
declare(strict_types=1);

namespace Panth\EuWithdrawal\Test\Unit\Model;

use Panth\EuWithdrawal\Model\Source\Status;
use Panth\EuWithdrawal\Model\StatusChangeValidator;
use PHPUnit\Framework\TestCase;

class StatusChangeValidatorTest extends TestCase
{
    public function testRejectingRequiresANonEmptyReason(): void
    {
        $validator = new StatusChangeValidator();

        $this->assertTrue($validator->requiresComment(Status::RECEIVED, Status::REJECTED));
        $error = $validator->validate(Status::RECEIVED, Status::REJECTED, '');
        $this->assertNotNull($error);
        $this->assertStringContainsString('reason for the rejection', (string)$error);
        $this->assertNotNull($validator->validate(Status::ACKNOWLEDGED, Status::REJECTED, " \n\t "));
        $this->assertNull($validator->validate(Status::RECEIVED, Status::REJECTED, 'Sent after the period ended.'));
    }

    public function testOtherStatusesAndUnchangedRejectionNeedNoReason(): void
    {
        $validator = new StatusChangeValidator();

        $this->assertFalse($validator->requiresComment(Status::REJECTED, Status::REJECTED));
        $this->assertNull($validator->validate(Status::REJECTED, Status::REJECTED, ''));
        $this->assertNull($validator->validate(Status::RECEIVED, Status::ACKNOWLEDGED, ''));
        $this->assertNull($validator->validate(Status::ACKNOWLEDGED, Status::REFUNDED, ''));
        $this->assertNull($validator->validate(Status::REJECTED, Status::RECEIVED, ''));
    }
}
