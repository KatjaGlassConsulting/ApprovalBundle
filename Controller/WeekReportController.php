<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Entity\Customer;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Model\DailyStatistic;
use App\Reporting\WeekByUser\WeekByUser;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTime;
use Doctrine\ORM\Exception\ORMException;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalWorkdayHistory;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Enumeration\FormEnum;
use KimaiPlugin\ApprovalBundle\Form\AddWorkdayHistoryForm;
use KimaiPlugin\ApprovalBundle\Form\SettingsForm;
use KimaiPlugin\ApprovalBundle\Form\WeekByUserForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalTimesheetRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalWorkdayHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ReportRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/approval')]
class WeekReportController extends BaseApprovalController
{
    public function __construct(
        private SettingsTool $settingsTool,
        private SecurityTool $securityTool,
        private UserRepository $userRepository,
        private ApprovalHistoryRepository $approvalHistoryRepository,
        private ApprovalRepository $approvalRepository,
        private ApprovalWorkdayHistoryRepository $approvalWorkdayHistoryRepository,
        private Formatting $formatting,
        private TimesheetRepository $timesheetRepository,
        private ApprovalTimesheetRepository $approvalTimesheetRepository,
        private BreakTimeCheckToolGER $breakTimeCheckToolGER,
        private ReportRepository $reportRepository
    ) {
    }

    #[Route(path: '/week_by_user', name: 'approval_bundle_report', methods: ['GET', 'POST'])]
    public function weekByUser(Request $request): Response
    {
        $users = $this->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];
        $dateTimeFactory = $this->getDateTimeFactory($firstUser);

        $values = new WeekByUser();
        $values->setUser($firstUser);
        $values->setDate($dateTimeFactory->getStartOfWeek());

        $form = $this->createFormForGetRequest(WeekByUserForm::class, $values, [
            'timezone' => $dateTimeFactory->getTimezone()->getName(),
            'start_date' => $values->getDate(),
            'users' => $users,
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($firstUser);
        }

        if ($values->getDate() === null) {
            $values->setDate($dateTimeFactory->getStartOfWeek());
        }

        $start = $dateTimeFactory->getStartOfWeek($values->getDate());
        $end = $dateTimeFactory->getEndOfWeek($values->getDate());
        $selectedUser = $values->getUser();
        $startWeek = $values->getDate();

        $previous = clone $start;
        $previous->modify('-1 week');

        $next = clone $start;
        $next->modify('+1 week');

        $approvals = $this->approvalRepository->findApprovalForUser($selectedUser, $start, $end);
        $data = $this->reportRepository->getDailyStatistic($selectedUser, $start, $end);
        $status = '';
        if ($approvals) {
            $approvalHistory = $this->approvalHistoryRepository->findLastStatus($approvals->getId());
            if ($approvalHistory !== null) {
                $status = $approvalHistory->getStatus()->getName();
            }
            $expectedDuration = $approvals->getExpectedDuration();
        } else {
            $expectedDuration = $this->approvalRepository->calculateExpectedDurationByUserAndDate($selectedUser, $start, $end);
        }

        [$timesheets, $errors] = $this->getTimesheets($selectedUser, $start, $end);

        $selectedUserSundayIssue = $selectedUser->isFirstDayOfWeekSunday();
        $currentUserSundayIssue = $this->getUser()->isFirstDayOfWeekSunday();

        $overtimeDuration = null;
        if ($this->settingsTool->isOvertimeCheckActive()) {
            // use actual year display, in case of "starting", use first approval date
            $overtimeDuration = $this->approvalRepository->getExpectedActualDurationsForYear($selectedUser, $end);
        }

        $canManageHimself = $this->securityTool->canViewAllApprovals() || ($this->securityTool->canViewTeamApprovals() &&
            ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY) == '1'));

        return $this->render('@Approval/report_by_user.html.twig', [
            'approve' => $this->parseToHistoryView($selectedUser, $startWeek),
            'week' => $this->formatting->parseDate($startWeek instanceof DateTime ? $startWeek : new DateTime($startWeek)),
            'box_id' => 'user-week-report-box',
            'form' => $form->createView(),
            'days' => new DailyStatistic($start, $end, $selectedUser),
            'rows' => $data,
            'user' => $selectedUser,
            'current' => $start,
            'next' => $next,
            'previous' => $previous,
            'approveId' => empty($approvals) ? 0 : $approvals->getId(),
            'status' => $status,
            'current_tab' => 'weekly_report',
            'canManageHimself' => $canManageHimself,
            'currentUser' => $this->getUser()->getId(),
            'expectedDuration' => $expectedDuration,
            'yearDuration' => $overtimeDuration,
            'isSuperAdmin' => $this->getUser()->isSuperAdmin(),
            'warningNoUsers' => empty($users),
            'errors' => $errors,
            'timesheet' => $timesheets,
            'approvePreviousWeeksMessage' => $this->approvalRepository->getNextApproveWeek($selectedUser),
            'selectedUserSundayIssue' => $selectedUserSundayIssue,
            'currentUserSundayIssue' => $currentUserSundayIssue
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }

    #[Route(path: '/to_approve', name: 'approval_bundle_to_approve', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('view_team_approval') or is_granted('view_all_approval')"))]
    public function toApprove(): Response
    {
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY) == '1') {
            $users = $this->getUsers(true);
        } else {
            $users = $this->getUsers(false);
        }

        $warningNoUsers = false;
        if (empty($users)) {
            $warningNoUsers = true;
            $users = [$this->getUser()];
        }

        $allRows = $this->approvalRepository->findAllWeek($users);

        $pastRows = [];
        $currentRows = [];
        $futureRows = [];
        $currentWeek = (new DateTime('now'))->modify('next monday')->modify('-2 week')->format('Y-m-d');
        $futureWeek = (new DateTime('now'))->modify('next monday')->modify('-1 week')->format('Y-m-d');
        foreach ($allRows as $row) {
            if ($row['startDate'] >= $futureWeek) {
                $futureRows[] = $row;
            } elseif ($row['startDate'] >= $currentWeek) {
                $currentRows[] = $row;
            } else {
                $pastRows[] = $row;
            }
        }
        $pastRows = $this->approvalRepository->filterPastWeeksNotApproved($pastRows);

        return $this->render('@Approval/to_approve.html.twig', [
            'current_tab' => 'to_approve',
            'past_rows' => $pastRows,
            'current_rows' => $currentRows,
            'future_rows' => $futureRows,
            'warningNoUsers' => $warningNoUsers
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }

    #[Route(path: '/settings', name: 'approval_bundle_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request): Response
    {
        return $this->render('@Approval/settings.html.twig', [
            'current_tab' => 'settings',
            'form' => $this->createSettingsForm($request),
            'warningNoUsers' => empty($this->getUsers())
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }

    #[Route(path: '/settings_workday_history', name: 'approval_bundle_settings_workday', methods: ['GET', 'POST'])]
    public function settingsWorkdayHistory(Request $request): Response
    {
        $workdayHistory = $this->approvalWorkdayHistoryRepository->findAll();

        return $this->render('@Approval/settings_workday_history.html.twig', [
            'current_tab' => 'settings_workday_history',
            'workdayHistory' => $workdayHistory,
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }

    #[Route(path: '/create_workday_history', name: 'approval_create_workday_history', methods: ['GET', 'POST'])]
    public function createWorkdayHistory(Request $request): Response
    {
        $users = $this->userRepository->findAll();

        $form = $this->createForm(AddWorkdayHistoryForm::class, $users, [
            'action' => $this->generateUrl('approval_create_workday_history'),
            'method' => 'POST'
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $workdayHistory = new ApprovalWorkdayHistory();

                $workdayHistory->setUserId($form->getData()['user']);
                $workdayHistory->setMonday($form->getData()['monday']);
                $workdayHistory->setTuesday($form->getData()['tuesday']);
                $workdayHistory->setWednesday($form->getData()['wednesday']);
                $workdayHistory->setThursday($form->getData()['thursday']);
                $workdayHistory->setFriday($form->getData()['friday']);
                $workdayHistory->setSaturday($form->getData()['saturday']);
                $workdayHistory->setSunday($form->getData()['sunday']);
                $workdayHistory->setValidTill($form->getData()['validTill']);

                $this->approvalWorkdayHistoryRepository->save($workdayHistory, true);
                $this->approvalTimesheetRepository->updateDaysOff($form->getData()['user']);
                $this->approvalRepository->updateExpectedActualDurationForUser($form->getData()['user']);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('approval_bundle_settings_workday');
            } catch (ORMException $e) {
                $this->flashUpdateException($e);
            }
        }

        return $this->render('@Approval/add_workday_history.html.twig', [
            'title' => 'title.add_workday_history',
            'form' => $form->createView()
        ]);
    }

    #[Route(path: '/deleteWorkdayHistory', name: 'delete_workday_history', methods: ['GET'])]
    public function deleteWorkdayHistoryAction(Request $request): Response
    {
        $entryId = $request->get('entryId');

        $workdayHistory = $this->approvalWorkdayHistoryRepository->find($entryId);
        if ($workdayHistory) {
            $user = $workdayHistory->getUser();

            $this->approvalWorkdayHistoryRepository->remove($workdayHistory, true);
            $this->approvalTimesheetRepository->updateDaysOff($user);
            $this->approvalRepository->updateExpectedActualDurationForUser($user);
        }

        return $this->redirectToRoute('approval_bundle_settings_workday');
    }

    private function createSettingsForm(Request $request): FormView
    {
        $form = $this->createForm(SettingsForm::class);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();
            $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EMAIL_LINK_URL, $data[FormEnum::EMAIL_LINK_URL]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_WORKFLOW_START, $data[FormEnum::WORKFLOW_START]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY, $data[FormEnum::OVERTIME_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY, $data[FormEnum::BREAKCHECKS_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_INCLUDE_ADMIN_NY, $data[FormEnum::INCLUDE_ADMIN_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_TEAMLEAD_SELF_APPROVE_NY, $data[FormEnum::TEAMLEAD_SELF_APPROVE_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS, $this->collectCustomerForFreeDays($data));
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_MAIL_SUBMITTED_NY, $data[FormEnum::MAIL_SUBMITTED_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_MAIL_ACTION_NY, $data[FormEnum::MAIL_ACTION_NY]);

            $this->flashSuccess('action.update.success');
        }

        return $form->createView();
    }

    private function getUsers(bool $includeOwnForTeam = true): array
    {
        if ($this->canManageAllPerson()) {
            $users = $this->userRepository->findAll();
        } elseif ($this->canManageTeam()) {
            $users = [];
            $user = $this->getUser();
            /** @var Team $team */
            foreach ($user->getTeams() as $team) {
                if (\in_array($user, $team->getTeamleads())) {
                    array_push($users, ...$team->getUsers());
                } else {
                    $users[] = $user;
                }
            }

            if (empty($users)) {
                $users = [$user];
            }

            $users = array_unique($users);

            if (!$includeOwnForTeam) {
                // remove the active user from the list
                $index = array_search($this->getUser(), $users);
                if ($index !== false) {
                    unset($users[$index]);
                }
            }
        } else {
            $users = [$this->getUser()];
        }

        $users = array_reduce($users, function ($current, $user) {
            $includeSuperAdmin = $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_INCLUDE_ADMIN_NY) == '1';
            if ($user->isEnabled() && (!$user->isSuperAdmin() || $includeSuperAdmin)) {
                $current[] = $user;
            }

            return $current;
        }, []);

        if (!empty($users)) {
            usort(
                $users,
                function (User $userA, User $userB) {
                    return strcmp(strtoupper($userA->getUsername()), strtoupper($userB->getUsername()));
                }
            );
        }

        return $users;
    }

    private function parseToHistoryView($userId, $startWeek): array
    {
        /** @var Approval[] $approvals */
        $approvals = $this->approvalRepository->findHistoryForUserAndWeek($userId, $startWeek);
        $approveArray = [];
        foreach ($approvals as $approval) {
            foreach ($approval->getHistory() as $history) {
                $approveArray[] = [
                    'username' => $history->getUser(),
                    'status' => $history->getStatus()->getName(),
                    'date' => $history->getDate(),
                    'message' => $history->getMessage()
                ];
            }
        }

        return $approveArray;
    }

    private function getTimesheets(?User $selectedUser, DateTime $start, DateTime $end): array
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
            $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY)) {
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

    private function collectCustomerForFreeDays($data)
    {
        if ($data[FormEnum::CUSTOMER_FOR_FREE_DAYS]) {
            /** @var Customer $customer */
            $customer = $data[FormEnum::CUSTOMER_FOR_FREE_DAYS];

            return $customer->getId();
        }

        return '';
    }
}
