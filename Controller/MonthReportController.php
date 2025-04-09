<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use Doctrine\ORM\Exception\ORMException;
use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Model\DailyStatistic;
use App\Repository\ActivityRepository;
use App\Reporting\MonthByUser;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalWorkdayHistory;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Enumeration\FormEnum;
use KimaiPlugin\ApprovalBundle\Form\SettingsForm;
use KimaiPlugin\ApprovalBundle\Form\MonthByUserForm;
use KimaiPlugin\ApprovalBundle\Form\AddWorkdayHistoryForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalTimesheetRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalWorkdayHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ReportRepository;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/approval")
 */
class MonthReportController extends AbstractController
{
    private $settingsTool;
    private $approvalRepository;
    private $approvalHistoryRepository;
    private $approvalWorkdayHistoryRepository;
    private $userRepository;
    private $formatting;
    private $timesheetRepository;
    private $approvalTimesheetRepository;
    private $breakTimeCheckToolGER;
    private $reportRepository;
    private $approvalSettings;
    private $securityTool;
    private $activityRepository;

    public function __construct(
        SettingsTool $settingsTool,
        SecurityTool $securityTool,
        UserRepository $userRepository,
        ApprovalHistoryRepository $approvalHistoryRepository,
        ApprovalRepository $approvalRepository,
        ApprovalWorkdayHistoryRepository $approvalWorkdayHistoryRepository,
        Formatting $formatting,
        TimesheetRepository $timesheetRepository,
        ApprovalTimesheetRepository $approvalTimesheetRepository,
        BreakTimeCheckToolGER $breakTimeCheckToolGER,
        ReportRepository $reportRepository,
        ActivityRepository $activityRepository,
        ApprovalSettingsInterface $approvalSettings
    ) {
        $this->settingsTool = $settingsTool;
        $this->securityTool = $securityTool;
        $this->userRepository = $userRepository;
        $this->approvalHistoryRepository = $approvalHistoryRepository;
        $this->approvalRepository = $approvalRepository;
        $this->approvalWorkdayHistoryRepository = $approvalWorkdayHistoryRepository;
        $this->formatting = $formatting;
        $this->timesheetRepository = $timesheetRepository;
        $this->approvalTimesheetRepository = $approvalTimesheetRepository;
        $this->breakTimeCheckToolGER = $breakTimeCheckToolGER;
        $this->reportRepository = $reportRepository;
        $this->approvalSettings = $approvalSettings;
        $this->activityRepository = $activityRepository;
    }

    /**
     * @Route(path="/month_by_user", name="approval_bundle_report_month", methods={"GET","POST"})
     * @throws Exception
     */
    public function monthByUser(Request $request): Response
    {
        $users = $this->securityTool->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];
        $dateTimeFactory = $this->getDateTimeFactory($firstUser);

        $values = new MonthByUser();
        $values->setUser($firstUser);
        $values->setDate($dateTimeFactory->getStartOfMonth());

        $form = $this->createForm(MonthByUserForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
            'users' => $users,
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($firstUser);
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->getStartOfMonth());
        }

        $start = $dateTimeFactory->getStartOfMonth($values->getDate());
        $end = $dateTimeFactory->getEndOfMonth($values->getDate());
        $selectedUser = $values->getUser();
        $startMonth = $values->getDate();

        $previous = clone $start;
        $previous->modify('-1 month');

        $next = clone $start;
        $next->modify('+1 month');

        $approvals = $this->approvalRepository->findApprovalsForUserWithStatus($selectedUser, $start, $end);
        
        $dayStatus = $this->getDayStatus($approvals, $start, $end);
        
        $data = $this->reportRepository->getDailyStatistic($selectedUser, $start, $end);
        
        [$timesheets, $errors] = $this->getTimesheets($selectedUser, $start, $end);

        $selectedUserSundayIssue = $selectedUser->isFirstDayOfWeekSunday();
        $currentUserSundayIssue = $this->getUser()->isFirstDayOfWeekSunday();

        $overtimeDuration = null;
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)){
            // use actual year display, in case of "starting", use first approval date
            $overtimeDuration = $this->approvalRepository->getExpectedActualDurationsForYear($selectedUser, $end);
        }
        $expectedDuration = $this->approvalRepository->calculateExpectedDurationByUserAndDate($selectedUser, $start, $end);

        $canManageHimself = $this->securityTool->canViewAllApprovals() || ($this->securityTool->canViewTeamApprovals() &&
            ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY) == "1"));

        return $this->render('@Approval/report_month_by_user.html.twig', [
            'month' => $this->formatting->parseDate($startMonth),
            'form' => $form->createView(),
            'days' => new DailyStatistic($start, $end, $selectedUser),
            'dayStatus' => $dayStatus,
            'rows' => $data,
            'user' => $selectedUser,
            'current' => $start,
            'next' => $next,
            'previous' => $previous,            
            'current_tab' => 'monthly_report',
            'canManageHimself' => $canManageHimself,
            'currentUser' => $this->getUser()->getId(),
            'showToApproveTab' => $this->securityTool->canViewAllApprovals() || $this->securityTool->canViewTeamApprovals(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'expectedDuration' => $expectedDuration,
            'yearDuration' => $overtimeDuration,
            'settingsWarning' => !$this->approvalSettings->isFullyConfigured(),
            'isSuperAdmin' => $this->getUser()->isSuperAdmin(),
            'warningNoUsers' => empty($users),
            'errors' => $errors,
            'timesheet' => $timesheets,
            'selectedUserSundayIssue' => $selectedUserSundayIssue,
            'currentUserSundayIssue' => $currentUserSundayIssue
        ]);
    }

    protected function getDayStatus($approvals, $start, $end) : ?array 
    {
        $dayStatus = [];

        if (empty($approvals)) {
            return [];
        }
        
        $startDate = $approvals[0]->getStartDate();

        // If $startDate > $start, fill the gap with "none"
        if ($startDate->format('Y-m-d') > $start->format('Y-m-d')) {
            $gapPeriod = new \DatePeriod(
                $start,
                new \DateInterval('P1D'),
                (clone $startDate)->modify('+1 day') // Include the startDate
            );

            foreach ($gapPeriod as $gapDay) {
                $gapDayFormatted = $gapDay->format('Y-m-d');
                $dayStatus[$gapDayFormatted] = 'none';
            }
        }

        foreach ($approvals as $approval) {
            $startDate = $approval->getStartDate();
            $endDate = $approval->getEndDate();
            $status = $approval->getHistory()[\count($approval->getHistory()) - 1]->getStatus()->getName();
        
            // Create a DatePeriod to iterate through each day in the range
            $period = new \DatePeriod(
                $startDate,
                new \DateInterval('P1D'), 
                (clone $endDate)->modify('+1 day') 
            );
        
            // Loop through each day in the period
            foreach ($period as $day) {
                $dayFormatted = $day->format('Y-m-d');
                if ($dayFormatted >= $start->format('Y-m-d') && $dayFormatted <= $end->format('Y-m-d')) {                    
                    $dayStatus[$dayFormatted] = $status;
                }
            }
        }
        return $dayStatus;
    }

    /**
     * @throws Exception
     */
    protected function getTimesheets(?User $selectedUser, DateTime $start, DateTime $end)
    {
        $timesheetQuery = new TimesheetQuery();
        $timesheetQuery->setUser($selectedUser);
        $dateRange = new DateRange();
        $dateRange->setBegin($start);
        $dateRange->setEnd($end);
        $timesheetQuery->setDateRange($dateRange);
        $timesheetQuery->setOrderBy('date');
        $timesheetQuery->setOrderBy('begin');
        $timesheetQuery->setOrder(BaseQuery::ORDER_ASC);

        $timesheets = $this->timesheetRepository->getTimesheetsForQuery($timesheetQuery);

        if ($this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY) == false or
            $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY)){
            $errors = $this->breakTimeCheckToolGER->checkBreakTime($timesheets);
        } else {
            $errors = [];
        }

        return [
            array_reduce(
                $timesheets,
                function ($result, Timesheet $timesheet) use ($errors) {
                    $date = $timesheet->getBegin()->format('Y-m-d');
                    if ($timesheet->getEnd()) {
                        $result[] = [
                            'date' => $date,
                            'begin' => $timesheet->getBegin()->format('H:i'),
                            'end' => $timesheet->getEnd()->format('H:i'),
                            'error' => \array_key_exists($date, $errors) ? $errors[$date] : [],
                            'duration' => $timesheet->getDuration(),
                            'customerName' => $timesheet->getProject()->getCustomer()->getName(),
                            'projectName' => $timesheet->getProject()->getName(),
                            'activityName' => $timesheet->getActivity()->getName(),
                            'description' => $timesheet->getDescription()
                        ];
                    }

                    return $result;
                },
                []
            ),
            $errors
        ];
    }
}
