<?php

namespace KimaiPlugin\ApprovalBundle\Tests\Service;

use App\Entity\User;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use DateTime;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Service\AutoApprovalService;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class AutoApprovalServiceTest extends TestCase
{
    private AutoApprovalService $service;
    private MockObject|ApprovalRepository $approvalRepository;
    private MockObject|TimesheetRepository $timesheetRepository;
    private MockObject|BreakTimeCheckToolGER $breakTimeCheckToolGER;
    private MockObject|SettingsTool $settingsTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approvalRepository = $this->createMock(ApprovalRepository::class);
        $this->timesheetRepository = $this->createMock(TimesheetRepository::class);
        $this->breakTimeCheckToolGER = $this->createMock(BreakTimeCheckToolGER::class);
        $this->settingsTool = $this->createMock(SettingsTool::class);

        $this->service = new AutoApprovalService(
            $this->approvalRepository,
            $this->timesheetRepository,
            $this->breakTimeCheckToolGER,
            $this->settingsTool
        );
    }

    /**
     * Test processApprovals with empty array
     */
    public function testProcessApprovalsWithEmptyArray(): void
    {
        $result = $this->service->processApprovals([]);

        $this->assertEquals(0, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['processedApprovals']);
    }

    /**
     * Test processApprovals with all successful approvals
     */
    public function testProcessApprovalsAllSuccessful(): void
    {
        $approvals = [
            $this->createMockApproval(1),
            $this->createMockApproval(2),
        ];

        // Mock repository to return valid approvals
        $this->approvalRepository->method('checkLastStatus')
            ->willReturnArgument(4);

        // Mock no errors
        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(false);

        $result = $this->service->processApprovals($approvals);

        $this->assertEquals(2, $result['successful']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(2, $result['processedApprovals']);
    }

    /**
     * Test processApprovals with some failures
     */
    public function testProcessApprovalsWithFailures(): void
    {
        $approval1 = $this->createMockApproval(1);
        $approval2 = $this->createMockApproval(2);

        $approvals = [$approval1, $approval2];

        // First approval succeeds, second fails
        $this->approvalRepository->method('checkLastStatus')
            ->willReturnOnConsecutiveCalls($approval1, null);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(false);

        $result = $this->service->processApprovals($approvals);

        $this->assertEquals(1, $result['successful']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['processedApprovals']);
    }

    /**
     * Test processSingleApproval with valid approval
     */
    public function testProcessSingleApprovalSuccess(): void
    {
        $approval = $this->createMockApproval(1);

        $this->approvalRepository->method('checkLastStatus')
            ->willReturn($approval);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(false);

        $result = $this->service->processSingleApproval($approval);

        $this->assertTrue($result['approved']);
        $this->assertSame($approval, $result['approval']);
        $this->assertEquals('Auto-approved successfully', $result['reason']);
    }

    /**
     * Test processSingleApproval with invalid status
     */
    public function testProcessSingleApprovalInvalidStatus(): void
    {
        $approval = $this->createMockApproval(1);

        $this->approvalRepository->method('checkLastStatus')
            ->willReturn(null);

        $result = $this->service->processSingleApproval($approval);

        $this->assertFalse($result['approved']);
        $this->assertNull($result['approval']);
        $this->assertEquals('Invalid approval status', $result['reason']);
    }

    /**
     * Test processSingleApproval with wrong status
     */
    public function testProcessSingleApprovalWrongStatus(): void
    {
        $approval = $this->createMockApprovalWithStatus(ApprovalStatus::APPROVED);

        $this->approvalRepository->method('checkLastStatus')
            ->willReturn($approval);

        $result = $this->service->processSingleApproval($approval);

        $this->assertFalse($result['approved']);
        $this->assertSame($approval, $result['approval']);
        $this->assertEquals('Not in submitted status', $result['reason']);
    }

    /**
     * Test processSingleApproval with validation errors
     */
    public function testProcessSingleApprovalWithValidationErrors(): void
    {
        $approval = $this->createMockApproval(1);

        $this->approvalRepository->method('checkLastStatus')
            ->willReturn($approval);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        // Mock break time errors
        $this->settingsTool->method('isInConfiguration')
            ->willReturn(true);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(true);

        $this->breakTimeCheckToolGER->method('checkBreakTime')
            ->willReturn(['2026-01-20' => 'Break time violation']);

        $result = $this->service->processSingleApproval($approval);

        $this->assertFalse($result['approved']);
        $this->assertSame($approval, $result['approval']);
        $this->assertEquals('Timesheet validation errors found', $result['reason']);
    }

    /**
     * Test getTimesheetsForApproval creates correct query
     */
    public function testGetTimesheetsForApprovalCreatesCorrectQuery(): void
    {
        $user = $this->createMock(User::class);
        $approval = $this->createMockApproval(1);
        $approval->method('getUser')->willReturn($user);

        $startDate = new DateTime('2026-01-20');
        $endDate = new DateTime('2026-01-26');

        $approval->method('getStartDate')->willReturn($startDate);
        $approval->method('getEndDate')->willReturn($endDate);

        $this->timesheetRepository->expects($this->once())
            ->method('getTimesheetsForQuery')
            ->willReturn([]);

        $result = $this->service->getTimesheetsForApproval($approval);

        $this->assertIsArray($result);
    }

    /**
     * Test getTimesheetsForApproval with actual timesheets
     */
    public function testGetTimesheetsForApprovalReturnsTimesheets(): void
    {
        $user = $this->createMock(User::class);
        $approval = $this->createMockApproval(1);
        $approval->method('getUser')->willReturn($user);

        $startDate = new DateTime('2026-01-20');
        $endDate = new DateTime('2026-01-26');

        $approval->method('getStartDate')->willReturn($startDate);
        $approval->method('getEndDate')->willReturn($endDate);

        // Mock timesheets to return
        $mockTimesheets = [
            ['date' => '2026-01-20', 'hours' => 8],
            ['date' => '2026-01-21', 'hours' => 8],
        ];

        $this->timesheetRepository->expects($this->once())
            ->method('getTimesheetsForQuery')
            ->willReturn($mockTimesheets);

        $result = $this->service->getTimesheetsForApproval($approval);

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test validateTimesheets with break checks enabled
     */
    public function testValidateTimesheetsWithBreakChecksEnabled(): void
    {
        $timesheets = [];
        $expectedErrors = ['2026-01-20' => 'Error'];

        $this->settingsTool->method('isInConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(true);

        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(true);

        $this->breakTimeCheckToolGER->expects($this->once())
            ->method('checkBreakTime')
            ->with($timesheets)
            ->willReturn($expectedErrors);

        $result = $this->service->validateTimesheets($timesheets);

        $this->assertEquals($expectedErrors, $result);
    }

    /**
     * Test validateTimesheets with break checks disabled
     */
    public function testValidateTimesheetsWithBreakChecksDisabled(): void
    {
        $timesheets = [];

        $this->settingsTool->method('isInConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(false);

        $this->breakTimeCheckToolGER->expects($this->never())
            ->method('checkBreakTime');

        $result = $this->service->validateTimesheets($timesheets);

        $this->assertEmpty($result);
    }

    /**
     * Test processApprovals handles mixed results correctly
     */
    public function testProcessApprovalsMixedResults(): void
    {
        $approval1 = $this->createMockApproval(1);
        $approval2 = $this->createMockApproval(2);
        $approval3 = $this->createMockApproval(3);

        $approvals = [$approval1, $approval2, $approval3];

        // First succeeds, second fails (null), third succeeds
        $this->approvalRepository->method('checkLastStatus')
            ->willReturnOnConsecutiveCalls($approval1, null, $approval3);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(false);

        $result = $this->service->processApprovals($approvals);

        $this->assertEquals(2, $result['successful']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(2, $result['processedApprovals']);
    }

    /**
     * Test processApprovals with break time errors on some approvals
     */
    public function testProcessApprovalsWithBreakTimeErrorsOnSome(): void
    {
        $approval1 = $this->createMockApproval(1);
        $approval2 = $this->createMockApproval(2);

        $approvals = [$approval1, $approval2];

        $this->approvalRepository->method('checkLastStatus')
            ->willReturnArgument(4);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(true);

        $this->settingsTool->method('getConfiguration')
            ->willReturn(true);

        // First has errors, second doesn't
        $this->breakTimeCheckToolGER->method('checkBreakTime')
            ->willReturnOnConsecutiveCalls(
                ['2026-01-20' => 'Error'],
                []
            );

        $result = $this->service->processApprovals($approvals);

        $this->assertEquals(1, $result['successful']);
        $this->assertEquals(1, $result['failed']);
    }

    /**
     * Helper method to create a mock approval
     */
    private function createMockApproval(int $id): MockObject|Approval
    {
        $approval = $this->createMock(Approval::class);
        $approval->method('getId')->willReturn($id);

        $user = $this->createMock(User::class);
        $approval->method('getUser')->willReturn($user);

        $startDate = new DateTime('2026-01-20');
        $endDate = new DateTime('2026-01-26');
        $approval->method('getStartDate')->willReturn($startDate);
        $approval->method('getEndDate')->willReturn($endDate);

        // Mock history with SUBMITTED status
        $history = $this->createMock(ApprovalHistory::class);
        $status = $this->createMock(ApprovalStatus::class);
        $status->method('getName')->willReturn(ApprovalStatus::SUBMITTED);
        $history->method('getStatus')->willReturn($status);

        $approval->method('getHistory')->willReturn([$history]);

        return $approval;
    }

    /**
     * Helper method to create a mock approval with specific status
     */
    private function createMockApprovalWithStatus(string $statusName): MockObject|Approval
    {
        $approval = $this->createMock(Approval::class);

        $user = $this->createMock(User::class);
        $approval->method('getUser')->willReturn($user);

        $startDate = new DateTime('2026-01-20');
        $endDate = new DateTime('2026-01-26');
        $approval->method('getStartDate')->willReturn($startDate);
        $approval->method('getEndDate')->willReturn($endDate);

        // Mock history with custom status
        $history = $this->createMock(ApprovalHistory::class);
        $status = $this->createMock(ApprovalStatus::class);
        $status->method('getName')->willReturn($statusName);
        $history->method('getStatus')->willReturn($status);

        $approval->method('getHistory')->willReturn([$history]);

        return $approval;
    }
}
