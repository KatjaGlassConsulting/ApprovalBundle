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
use App\Entity\Customer;
use App\Entity\Team;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Form\Model\DateRange;
use App\Model\DailyStatistic;
use App\Reporting\WeekByUser;
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
use KimaiPlugin\ApprovalBundle\Form\WeekByUserForm;
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
use PhpOffice\PhpSpreadsheet\Calculation\Logical\Boolean;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/approval')]
class WeekReportController extends AbstractController
{
    public function __construct(
        private SettingsTool $settingsTool,
        private UserRepository $userRepository,
        private ApprovalHistoryRepository $approvalHistoryRepository,
        private ApprovalRepository $approvalRepository,
        private ApprovalWorkdayHistoryRepository $approvalWorkdayHistoryRepository,
        private Formatting $formatting,
        private TimesheetRepository $timesheetRepository,
        private ApprovalTimesheetRepository $approvalTimesheetRepository,
        private BreakTimeCheckToolGER $breakTimeCheckToolGER,
        private ReportRepository $reportRepository,
        private ApprovalSettingsInterface $approvalSettings
    ) {
    }

    private function canManageTeam(): bool
    {
        return $this->isGranted('view_team_approval');
    }

    private function canManageAllPerson(): bool
    {
        return $this->isGranted('view_all_approval');
    }

    #[Route(path: '/week_by_user', name: 'approval_bundle_report', methods: ["GET","POST"])]
    public function weekByUser(Request $request): Response
    {
        $users = $this->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];
        $dateTimeFactory = $this->getDateTimeFactory($firstUser);

        $values = new WeekByUser();
        $values->setUser($firstUser);
        $values->setDate($dateTimeFactory->getStartOfWeek());

        $form = $this->createForm(WeekByUserForm::class, $values, [
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

        $previous = clone $start;
        $previous->modify('-1 week');

        $next = clone $start;
        $next->modify('+1 week');

        $approvals = $this->approvalRepository->findApprovalForUser($selectedUser, $start, $end);
        $data = $this->reportRepository->getDailyStatistic($selectedUser, $start, $end);
        if ($approvals) {
            $approvalHistory = $this->approvalHistoryRepository->findLastStatus($approvals->getId());
            $status = $approvalHistory->getStatus()->getName();
            $expectedDuration = $approvals->getExpectedDuration();
        } else {
            $status = '';
            $expectedDuration = $this->approvalRepository->calculateExpectedDurationByUserAndDate($selectedUser, $start, $end);
        }

        $userId = $request->query->get('user');
        $startWeek = $request->query->get('date');

        [$timesheets, $errors] = $this->getTimesheets($selectedUser, $start, $end);

        $selectedUserSundayIssue = $selectedUser->isFirstDayOfWeekSunday();
        $currentUserSundayIssue = $this->getUser()->isFirstDayOfWeekSunday();

        $overtimeDuration = null;
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)){
            // use actual year display, in case of "starting", use first approval date
            $overtimeDuration = $this->approvalRepository->getExpectedActualDurationsForYear($selectedUser, $end); 
        }

        return $this->render('@Approval/report_by_user.html.twig', [
            'approve' => $this->parseToHistoryView($userId, $startWeek),
            'week' => $this->formatting->parseDate(new DateTime($startWeek)),
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
            'canManageHimself' => (
                $this->canManageTeam() && !$this->isGranted('ROLE_SUPER_ADMIN')
            ) || !(
                $this->canManageTeam() && $this->canManageAllPerson()
            ),
            'currentUser' => $this->getUser()->getId(),
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
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
            'approvePreviousWeeksMessage' => $this->approvalRepository->getNextApproveWeek($selectedUser),
            'selectedUserSundayIssue' => $selectedUserSundayIssue,
            'currentUserSundayIssue' => $currentUserSundayIssue
        ]);
    }

    #[Route(path: '/to_approve', name: 'approval_bundle_to_approve', methods: ["GET","POST"])]
    #[IsGranted(new Expression("is_granted('view_team_approval') or is_granted('view_all_approval')"))]
    public function toApprove(): Response
    {
        $users = $this->getUsers(false);

        $warningNoUsers = false;
        if (empty($users)){
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
            }
            else if ($row['startDate'] >= $currentWeek) {
                $currentRows[] = $row;
            } else {
                $pastRows[] = $row;
            }
        }
        $pastRows = $this->approvalRepository->filterPastWeeksNotApproved($pastRows);
        $pastRows = $this->reduceRows($pastRows);
        $currentRows = $this->reduceRows($currentRows);
        $futureRows = $this->reduceRows($futureRows);

        return $this->render('@Approval/to_approve.html.twig', [
            'current_tab' => 'to_approve',
            'past_rows' => $pastRows,
            'current_rows' => $currentRows,
            'future_rows' => $futureRows,
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'settingsWarning' => !$this->approvalSettings->isFullyConfigured(),
            'warningNoUsers' => $warningNoUsers
        ]);
    }

    #[Route(path: '/settings', name: 'approval_bundle_settings', methods: ["GET","POST"])]
    public function settings(Request $request): Response
    {
        return $this->render('@Approval/settings.html.twig', [
            'current_tab' => 'settings',
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'form' => $this->createSettingsForm($request),
            'settingsWarning' => !$this->approvalSettings->isFullyConfigured(),
            'warningNoUsers' => empty($this->getUsers())
        ]);
    }

    #[Route(path: '/settings_workday_history', name: 'approval_bundle_settings_workday', methods: ["GET","POST"])]
    public function settingsWorkdayHistory(Request $request): Response
    {
        $workdayHistory = $this->approvalWorkdayHistoryRepository->findAll();
         
        return $this->render('@Approval/settings_workday_history.html.twig', [
            'current_tab' => 'settings_workday_history',
            'workdayHistory' => $workdayHistory,
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)
        ]);
    }

    #[Route(path: '/create_workday_history', name: 'approval_create_workday_history', methods: ["GET", "POST"])]
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

    #[Route(path: '/deleteWorkdayHistory', name: 'delete_workday_history', methods: ["GET"])]
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

    private function createSettingsForm(Request $request)
    {
        $form = $this->createForm(SettingsForm::class, null, [
            'with_time' => $this->approvalSettings->canBeConfigured()
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $data = $form->getData();

            if ($this->approvalSettings->canBeConfigured()) {
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_MONDAY, $this->collectMetaField($data, FormEnum::MONDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_TUESDAY, $this->collectMetaField($data, FormEnum::TUESDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_WEDNESDAY, $this->collectMetaField($data, FormEnum::WEDNESDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_THURSDAY, $this->collectMetaField($data, FormEnum::THURSDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_FRIDAY, $this->collectMetaField($data, FormEnum::FRIDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SATURDAY, $this->collectMetaField($data, FormEnum::SATURDAY));
                $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SUNDAY, $this->collectMetaField($data, FormEnum::SUNDAY));
            }
            $this->settingsTool->setConfiguration(ConfigEnum::META_FIELD_EMAIL_LINK_URL, $data[FormEnum::EMAIL_LINK_URL]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_WORKFLOW_START, $data[FormEnum::WORKFLOW_START]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY, $data[FormEnum::OVERTIME_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY, $data[FormEnum::BREAKCHECKS_NY]);
            $this->settingsTool->setConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS, $this->collectCustomerForFreeDays($data));

            $this->flashSuccess('action.update.success');
        }

        return $form->createView();
    }

    private function collectMetaField($data, $key)
    {
        return !empty($data[$key]) ? ($data[$key])->getId() : '';
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

            if (!$includeOwnForTeam)
            {
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
            if ($user->isEnabled() && !$user->isSuperAdmin()) {
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

    protected function getTimesheets(?User $selectedUser, DateTime $start, DateTime $end): array
    {
        $timesheetQuery = new TimesheetQuery();
        $timesheetQuery->setUser($selectedUser);
        $dateRange = new DateRange();
        $dateRange->setBegin($start);
        $dateRange->setEnd($end);
        $timesheetQuery->setDateRange($dateRange);
        $timesheetQuery->setOrderBy('date');
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

    private function collectCustomerForFreeDays($data)
    {
        if ($data[FormEnum::CUSTOMER_FOR_FREE_DAYS]) {
            /** @var Customer $customer */
            $customer = $data[FormEnum::CUSTOMER_FOR_FREE_DAYS];

            return $customer->getId();
        }

        return '';
    }

    private function reduceRows(array $rows): array
    {
        return array_reduce($rows, function ($toReturn, $row) {
            $currentUser = $this->getUser();
            $isCurrentUserATeamLeader = \in_array('ROLE_TEAMLEAD', $currentUser->getRoles());
            if (!($row['user'] === $currentUser->getUsername() && $isCurrentUserATeamLeader && $row['status'] !== 'not_submitted')) {
                $toReturn[] = $row;
            }

            return $toReturn;
        }, []);
    }
}
