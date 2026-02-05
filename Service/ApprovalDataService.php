<?php

namespace KimaiPlugin\ApprovalBundle\Service;

use App\Entity\User;
use App\Form\Model\DateRange;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTime;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\Query\ApprovalQuery;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;

class ApprovalDataService
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private UserRepository $userRepository,
        private TimesheetRepository $timesheetRepository,
        private BreakTimeCheckToolGER $breakTimeCheckToolGER,
        private SettingsTool $settingsTool
    ) {
    }

    public function fetchAndFilterApprovalRows(ApprovalQuery $query, array $users): array
    {
        $rows = $this->approvalRepository->findAllWeek($users);

        if ($this->settingsTool->getBooleanConfiguration(ConfigEnum::APPROVAL_HIDE_APPROVED_NY, false)) {
            $rows = $this->approvalRepository->filterWeeksNotApproved($rows);
        }

        $rows = $this->filterByUsers($rows, $query->getUsers());
        $rows = $this->filterByDateRange($rows, $query->getDateRange());
        $rows = $this->filterByStatus($rows, $query->getStatus());
        $rows = $this->filterBySearchTerm($rows, $query->getSearchTerm());

        return $rows;
    }

    public function enrichRowsWithErrors(array $rows): array
    {
        foreach ($rows as &$row) {
            $timesheets = $this->getTimesheetsForRow($row);
            $errors = $this->getBreakTimeErrors($timesheets);
            $row['hasErrors'] = !empty(array_filter($errors));
        }
        return $rows;
    }

    public function categorizeRowsByWeek(array $rows): array
    {
        $currentWeek = (new DateTime('now'))->modify('next monday')->modify('-1 week')->format('Y-m-d');
        $futureWeek = (new DateTime('now'))->modify('next monday')->format('Y-m-d');

        $pastRows = [];
        $currentRows = [];
        $futureRows = [];

        foreach ($rows as $row) {
            if ($row['startDate'] >= $futureWeek) {
                $futureRows[] = $row;
            } elseif ($row['startDate'] >= $currentWeek) {
                $currentRows[] = $row;
            } else {
                $pastRows[] = $row;
            }
        }

        return [$pastRows, $currentRows, $futureRows];
    }

    public function countSubmittedWeeks(array $rows): int
    {
        return count(array_filter(
            $rows,
            fn($row) =>
            isset($row['status']) && $row['status'] === "submitted"
        ));
    }

    private function filterByUsers(array $rows, array $selectedUsers): array
    {
        if (empty($selectedUsers)) {
            return $rows;
        }

        $selectedUserIds = array_map(fn(User $user) => $user->getId(), $selectedUsers);
        return array_filter($rows, fn($row) => in_array($row['userId'], $selectedUserIds));
    }

    private function filterByDateRange(array $rows, ?DateRange $dateRange): array
    {
        if ($dateRange === null || $dateRange->getBegin() === null || $dateRange->getEnd() === null) {
            return $rows;
        }

        $begin = $dateRange->getBegin();
        $end = $dateRange->getEnd();

        return array_filter($rows, function ($row) use ($begin, $end) {
            $weekStart = $row['week']->value;
            $weekEnd = (clone $weekStart)->modify('+6 days');
            return ($weekStart >= $begin && $weekStart <= $end) ||
                ($weekEnd >= $begin && $weekEnd <= $end);
        });
    }

    private function filterByStatus(array $rows, array $selectedStatus): array
    {
        if (empty($selectedStatus)) {
            return $rows;
        }

        return array_filter($rows, fn($row) => in_array($row['status'], $selectedStatus));
    }

    private function filterBySearchTerm(array $rows, $searchTerm): array
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
            mb_strtolower($row['user']),
            mb_strtolower($row['week']->label ?? ''),
            mb_strtolower($row['status'])
        ];

        foreach ($searchableFields as $field) {
            if (str_contains($field, $term)) {
                return true;
            }
        }

        return false;
    }

    private function getTimesheetsForRow(array $row): array
    {
        $timesheetQuery = new TimesheetQuery();
        $timesheetQuery->setUser($this->userRepository->find($row['userId']));

        $dateRange = new DateRange();
        $dateRange->setBegin($row['week']->value);
        $dateRange->setEnd((clone $row['week']->value)->modify('+6 days')->setTime(23, 59, 59));
        $timesheetQuery->setDateRange($dateRange);
        $timesheetQuery->setOrderBy('date');
        $timesheetQuery->setOrder(BaseQuery::ORDER_ASC);

        return $this->timesheetRepository->getTimesheetsForQuery($timesheetQuery);
    }

    private function getBreakTimeErrors(array $timesheets): array
    {
        if (
            !$this->settingsTool->isInConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY) &&
            !$this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
        ) {
            return [];
        }

        return $this->breakTimeCheckToolGER->checkBreakTime($timesheets);
    }
}