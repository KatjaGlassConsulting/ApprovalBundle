<?php

namespace KimaiPlugin\ApprovalBundle\Service;

use App\Form\Model\DateRange;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use DateTime;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;

class AutoApprovalService
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private TimesheetRepository $timesheetRepository,
        private BreakTimeCheckToolGER $breakTimeCheckToolGER,
        private SettingsTool $settingsTool
    ) {
    }

    /**
     * Process multiple approvals for auto-approval
     * 
     * @param Approval[] $approvals
     * @return array{successful: int, failed: int, processedApprovals: Approval[]}
     */
    public function processApprovals(array $approvals): array
    {
        $countSuccessful = 0;
        $countFailed = 0;
        $processedApprovals = [];

        foreach ($approvals as $approval) {
            $result = $this->processSingleApproval($approval);

            if ($result['approved']) {
                $countSuccessful++;
                $processedApprovals[] = $result['approval'];
            } else {
                $countFailed++;
            }
        }

        return [
            'successful' => $countSuccessful,
            'failed' => $countFailed,
            'processedApprovals' => $processedApprovals
        ];
    }

    /**
     * Process a single approval for auto-approval
     * 
     * @return array{approved: bool, approval: ?Approval, reason: string}
     */
    public function processSingleApproval(Approval $approval): array
    {
        // Check if approval has correct status
        $approval = $this->approvalRepository->checkLastStatus(
            $approval->getStartDate(),
            $approval->getEndDate(),
            $approval->getUser(),
            ApprovalStatus::SUBMITTED,
            $approval
        );

        if (!$approval) {
            return [
                'approved' => false,
                'approval' => null,
                'reason' => 'Invalid approval status'
            ];
        }

        // Verify the last status is SUBMITTED
        if ($approval->getHistory()[0]->getStatus()->getName() !== ApprovalStatus::SUBMITTED) {
            return [
                'approved' => false,
                'approval' => $approval,
                'reason' => 'Not in submitted status'
            ];
        }

        // Get timesheets for validation
        $timesheets = $this->getTimesheetsForApproval($approval);

        // Check for errors
        $errors = $this->validateTimesheets($timesheets);
        $hasErrors = !empty(array_filter($errors));

        if ($hasErrors) {
            return [
                'approved' => false,
                'approval' => $approval,
                'reason' => 'Timesheet validation errors found'
            ];
        }

        // Approval can be auto-approved
        return [
            'approved' => true,
            'approval' => $approval,
            'reason' => 'Auto-approved successfully'
        ];
    }

    /**
     * Get timesheets for an approval period
     */
    public function getTimesheetsForApproval(Approval $approval): array
    {
        $timesheetQuery = new TimesheetQuery();
        $timesheetQuery->setUser($approval->getUser());

        $dateRange = new DateRange();
        $dateRange->setBegin($approval->getStartDate());
        $dateRange->setEnd((clone $approval->getEndDate())->setTime(23, 59, 59));
        $timesheetQuery->setDateRange($dateRange);
        $timesheetQuery->setOrderBy('date');
        $timesheetQuery->setOrder(BaseQuery::ORDER_ASC);

        return $this->timesheetRepository->getTimesheetsForQuery($timesheetQuery);
    }

    /**
     * Validate timesheets for break time errors
     */
    public function validateTimesheets(array $timesheets): array
    {
        if (!$this->shouldCheckBreakTime()) {
            return [];
        }

        return $this->breakTimeCheckToolGER->checkBreakTime($timesheets);
    }

    /**
     * Check if break time validation is enabled
     */
    private function shouldCheckBreakTime(): bool
    {
        return $this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY) &&
            $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY);

    }
}
