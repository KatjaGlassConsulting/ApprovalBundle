<?php

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Entity\User;
use App\Form\Model\DateRange;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Repository\Query\TimesheetQuery;
use App\Repository\Query\BaseQuery;
use App\Utils\SearchTerm;
use DateTimeInterface;
use Pagerfanta\Adapter\ArrayAdapter;
use App\Utils\DataTable;
use App\Utils\Pagination;
use DateTime;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Toolbox\WorkingTimeActToolGER;
use KimaiPlugin\ApprovalBundle\Form\Toolbar\WorkingTimeActToolbarForm;
use KimaiPlugin\ApprovalBundle\Repository\Query\WorkingTimeActQuery;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/approval')]
class WorkingTimeActGERController extends BaseApprovalController
{

    public function __construct(
        private TimesheetRepository $timesheetRepository,
        private UserRepository $userRepository,
        private BreakTimeCheckToolGER $breakTimeCheckToolGER,
        private WorkingTimeActToolGER $workingTimeActToolGER,
        private SettingsTool $settingsTool
    ) {
    }

    #[Route(path: '/workingtimeactger', name: 'approval_bundle_working_time_act_ger', methods: ['GET', 'POST'])]
    public function workingTimeActCheck(WorkingTimeActQuery $query, Request $request): Response
    {
        if (!$this->isGranted('ROLE_SUPER_ADMIN')) {
            throw $this->createAccessDeniedException();
        }

        $form = $this->getToolbarForm($query);
        if ($this->handleSearch($form, $request)) {
            return $this->redirectToRoute('approval_bundle_working_time_act_ger');
        }

        $users = $this->getUsersToProcess($query);
        $results = $this->processAllUsersCompliance($users, $query);
        $results = $this->filterBySearchTerm($results, $query->getSearchTerm());
        $results = $this->sortArrayByQuery($results, $query);

        $dataTable = $this->buildDataTable($query, $results, $form);

        return $this->render('@Approval/working_time_act_ger_check.html.twig', array_merge($this->getDefaultTemplateParams($this->settingsTool), [
            "current_tab" => "working_time_act_ger_check",
            "dataTable" => $dataTable,
        ]));
    }

    /**
     * Get the list of users to process for compliance checking
     *
     * @param WorkingTimeActQuery $query
     * @return User[]
     */
    private function getUsersToProcess(WorkingTimeActQuery $query): array
    {
        return $query->getUsers() ?: $this->userRepository->findAll();
    }

    /**
     * Process compliance for all users
     *
     * @param User[] $users
     * @param WorkingTimeActQuery $query
     * @return array
     */
    private function processAllUsersCompliance(array $users, WorkingTimeActQuery $query): array
    {
        $results = [];
        $hasCustomDateRange = $this->hasCustomDateRange($query);

        foreach ($users as $user) {
            $result = $hasCustomDateRange
                ? $this->processCustomDateRangeCompliance($user, $query)
                : $this->processDefaultPeriodsCompliance($user);

            $results[] = $result;
        }

        return $results;
    }

    /**
     * Check if query has a custom date range
     *
     * @param WorkingTimeActQuery $query
     * @return bool
     */
    private function hasCustomDateRange(WorkingTimeActQuery $query): bool
    {
        return $query->getDateRange()
            && $query->getDateRange()->getBegin()
            && $query->getDateRange()->getEnd();
    }

    /**
     * Process compliance for custom date range
     *
     * @param User $user
     * @param WorkingTimeActQuery $query
     * @return array
     */
    private function processCustomDateRangeCompliance(User $user, WorkingTimeActQuery $query): array
    {
        $periodStart = $query->getDateRange()->getBegin();
        $periodEnd = $query->getDateRange()->getEnd();

        $timesheets = $this->getTimesheets($user, $periodStart, $periodEnd);
        $publicHolidayGroupId = $this->getPublicHolidayGroupId($user);

        $complianceResult = $this->workingTimeActToolGER->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            $publicHolidayGroupId,
            $periodStart,
            $periodEnd
        );

        return [
            'user' => $user->getDisplayName(),
            'average_daterange' => round($complianceResult['average'], 2),
        ];
    }

    /**
     * Process compliance for default periods (6 months and 24 weeks)
     *
     * @param User $user
     * @return array
     */
    private function processDefaultPeriodsCompliance(User $user): array
    {
        $periodEnd = new DateTime();
        $periodStart6Months = new DateTime('-6 month');
        $periodStart24Weeks = new DateTime('-24 week');

        $publicHolidayGroupId = $this->getPublicHolidayGroupId($user);

        // Get timesheets for 6 months (covers both periods)
        $timesheets6Months = $this->getTimesheets($user, $periodStart6Months, $periodEnd);

        $complianceResult6Months = $this->workingTimeActToolGER->checkWorkingTimeActToolGERCompliance(
            $timesheets6Months,
            $publicHolidayGroupId,
            $periodStart6Months,
            $periodEnd
        );

        // Filter timesheets for 24 weeks period
        $timesheets24Weeks = $this->filterTimesheetsByDate($timesheets6Months, $periodStart24Weeks);

        $complianceResult24Weeks = $this->workingTimeActToolGER->checkWorkingTimeActToolGERCompliance(
            $timesheets24Weeks,
            $publicHolidayGroupId,
            $periodStart24Weeks,
            $periodEnd
        );

        return [
            'user' => $user->getDisplayName(),
            'average_6months' => round($complianceResult6Months['average'], 2),
            'average_24weeks' => round($complianceResult24Weeks['average'], 2),
            'compliance' => $complianceResult6Months['compliance'] && $complianceResult24Weeks['compliance'],
        ];
    }

    /**
     * Get public holiday group ID for a user
     *
     * @param User $user
     * @return int|null
     */
    private function getPublicHolidayGroupId(User $user): ?int
    {
        return $user->getPublicHolidayGroup() ? (int) $user->getPublicHolidayGroup() : null;
    }

    /**
     * Filter timesheets by minimum date
     *
     * @param array $timesheets
     * @param DateTime $minDate
     * @return array
     */
    private function filterTimesheetsByDate(array $timesheets, DateTime $minDate): array
    {
        return array_filter($timesheets, function ($timesheet) use ($minDate) {
            return $timesheet->getBegin() >= $minDate;
        });
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

        return $this->timesheetRepository->getTimesheetsForQuery($timesheetQuery);

    }

    /**
     * Check ArbZG compliance for timesheets
     */
    /**
     * Build DataTable for Working Time Act compliance results
     */
    private function buildDataTable(WorkingTimeActQuery $query, array $rows, FormInterface $form): DataTable
    {
        $dataTable = new DataTable('working_time_act_check', $query);
        $dataTable->deactivateConfiguration();
        $dataTable->addColumn("user", ['label' => 'label.user']);

        if ($query->getDateRange() && $query->getDateRange()->getBegin() && $query->getDateRange()->getEnd()) {
            $dataTable->addColumn("label.average_daterange", ['label' => 'label.average_daterange']);
        } else {
            $dataTable->addColumn("label.average_6months", ['label' => 'label.average_6months']);
            $dataTable->addColumn("label.average_24weeks", ['label' => 'label.average_24']);
            $dataTable->addColumn("label.compliance", ['label' => 'label.compliance', 'type' => 'boolean']);
        }

        $adapter = new ArrayAdapter($rows);
        $pagination = new Pagination($adapter);
        $dataTable->setPagination($pagination);
        $dataTable->setSearchForm($form);

        return $dataTable;
    }

    protected function getToolbarForm(WorkingTimeActQuery $query): FormInterface
    {
        return $this->createSearchForm(WorkingTimeActToolbarForm::class, $query, [
            'action' => $this->generateUrl('approval_bundle_working_time_act_ger', [
                'page' => $query->getPage(),
            ]),
            'timezone' => $this->getDateTimeFactory()->getTimezone()->getName(),
        ]);
    }

    private function filterBySearchTerm(array $rows, ?SearchTerm $searchTerm): array
    {
        if ($searchTerm === null || empty($searchTerm->getSearchTerm())) {
            return $rows;
        }

        $searchParts = $searchTerm->getParts();

        return array_filter($rows, function ($row) use ($searchParts) {
            foreach ($searchParts as $part) {
                if (!$this->rowMatchesSearchTerm($row, mb_strtolower($part->getTerm()))) {
                    return false;
                }
            }
            return true;
        });
    }

    private function rowMatchesSearchTerm(array $row, string $term): bool
    {
        $searchableFields = [
            mb_strtolower($row['user'] ?? ''),
            (string) ($row['average_6months'] ?? ''),
            (string) ($row['average_24weeks'] ?? ''),
        ];

        foreach ($searchableFields as $field) {
            if (str_contains($field, $term)) {
                return true;
            }
        }

        return false;
    }

    private function sortArrayByQuery(array $rows, WorkingTimeActQuery $query): array
    {
        $orderBy = $query->getOrderBy();
        $order = $query->getOrder();

        if (!$orderBy || !in_array($orderBy, WorkingTimeActQuery::ORDER_ALLOWED, true)) {
            return $rows;
        }

        usort($rows, function ($a, $b) use ($orderBy, $order) {
            $valueA = $a[$orderBy] ?? null;
            $valueB = $b[$orderBy] ?? null;

            if (is_string($valueA) || is_string($valueB)) {
                $valueA = mb_strtolower((string) $valueA);
                $valueB = mb_strtolower((string) $valueB);
            }

            if ($valueA == $valueB) {
                return 0;
            }

            $result = ($valueA < $valueB) ? -1 : 1;
            return $order === BaseQuery::ORDER_DESC ? -$result : $result;
        });

        return $rows;
    }

}