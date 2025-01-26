<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\TimesheetRepository;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @method Approval|null find($id, $lockMode = null, $lockVersion = null)
 * @method Approval|null findOneBy(array $criteria, array $orderBy = null)
 * @method Approval[] findAll()
 * @method Approval[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApprovalRepository extends ServiceEntityRepository
{
    /**
     * @var SettingsTool
     */
    private $settingsTool;

    /**
     * @var ApprovalSettingsInterface
     */
    private $metaFieldRuleRepository;

    /**
     * @var Formatting
     */
    private $formatting;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var ApprovalWorkdayHistoryRepository
     */
    private $approvalWorkdayHistoryRepository;

    /**
     * @var ApprovalOvertimeHistoryRepository
     */
    private $approvalOvertimeHistoryRepository;

    /**
     * @var ReportRepository
     */
    private $reportRepository;

    /**
     * @var TimesheetRepository
     */
    private $timesheetRepository;

    public function __construct(
        ManagerRegistry $registry,
        ApprovalSettingsInterface $metaFieldRuleRepository,
        ApprovalWorkdayHistoryRepository $approvalWorkdayHistoryRepository,
        ApprovalOvertimeHistoryRepository $approvalOvertimeHistoryRepository,
        ReportRepository $reportRepository,
        TimesheetRepository $timesheetRepository,
        SettingsTool $settingsTool,
        Formatting $formatting,
        UrlGeneratorInterface $urlGenerator
    ) {
        parent::__construct($registry, Approval::class);
        $this->metaFieldRuleRepository = $metaFieldRuleRepository;
        $this->approvalWorkdayHistoryRepository = $approvalWorkdayHistoryRepository;
        $this->approvalOvertimeHistoryRepository = $approvalOvertimeHistoryRepository;
        $this->reportRepository = $reportRepository;
        $this->timesheetRepository = $timesheetRepository;
        $this->settingsTool = $settingsTool;
        $this->formatting = $formatting;
        $this->urlGenerator = $urlGenerator;
    }

    public function createApproval(string $data, User $user): ?Approval
    {
        $startDate = new DateTime($data);
        $endDate = (clone $startDate)->modify('next sunday');
        date_time_set($endDate, 23, 59, 59);

        $approval = $this->checkLastStatus($startDate, $endDate, $user, ApprovalStatus::NOT_SUBMITTED, new Approval());

        if ($approval) {
            $approval->setUser($user);
            $approval->setCreationDate(new DateTime());
            $approval->setStartDate($startDate);
            $approval->setEndDate($endDate);
            $approval->setExpectedDuration($this->calculateExpectedDurationByUserAndDate($user, $startDate, $endDate));
            $approval->setActualDuration($this->reportRepository->getActualWorkingDurationStatistic($user, $startDate, $endDate));

            $this->getEntityManager()->persist($approval);
            $this->getEntityManager()->flush();
        }

        return $approval;
    }

    public function getExpectedActualDurationsForYear(User $user, \DateTime $endDate): ?array
    {
        $end = clone $endDate;
        date_time_set($end, 23, 59, 59);
        $firstApprovalDate = $this->findFirstApprovalDateForUser($user);
        if ($firstApprovalDate !== null) {
            $yearOfEnd = $endDate->format('Y');
            $firstOfYear = new \DateTime("$yearOfEnd-01-01");
            $startDurationYear = max($firstApprovalDate, $firstOfYear);

            $adoptionUntil = clone $startDurationYear;
            $adoptionUntil->modify('-1 day');
            $manualAdoption = $this->approvalOvertimeHistoryRepository->getOvertimeCorrectionForUserByStardEndDate($user, $firstOfYear, $adoptionUntil);

            $overtimeDuration = $this->getExpectedActualDurations($user, $startDurationYear, $end, $manualAdoption);

            return $overtimeDuration;
        }

        return null;
    }

    public function getExpectedActualDurations(User $user, \DateTime $startDate, \DateTime $endDate, $additionalAdd = 0): ?array
    {
        $expectedDuration = $this->calculateExpectedDurationByUserAndDate($user, $startDate, $endDate);
        $actualDuration = $this->timesheetRepository->getDurationForTimeRange($startDate, $endDate, $user);
        $overtime = $actualDuration - $expectedDuration;

        $manualAdoption = $this->approvalOvertimeHistoryRepository->getOvertimeCorrectionForUserByStardEndDate($user, $startDate, $endDate);
        $overtime = $overtime + $manualAdoption + $additionalAdd;

        $overtimeFormatted = $this->formatting->formatDuration($overtime);
        $result =
        [
            'expectedDuration' => $expectedDuration,
            'actualDuration' => $actualDuration,
            'overtime' => $overtime,
            'overtimeFormatted' => $overtimeFormatted,
            'manualAdoption' => $manualAdoption,
            'endDay' => $endDate->format('Y-m-d')
        ];

        return $result;
    }

    public function calculateExpectedDurationByUserAndDate($user, $startDate, $endDate): int
    {
        $expected = 0;
        for ($i = clone $startDate; $i <= $endDate; $i->modify('+1 day')) {
            $expected = $this->getExpectTimeForDate($i, $user, $expected);
        }

        return $expected;
    }

    public function getExpectTimeForDate(DateTime $i, User $user, $expected)
    {
        $workdayHistory = $this->approvalWorkdayHistoryRepository->findByUserAndDateWorkdayHistory($user, $i);

        if (\is_null($workdayHistory)) {
            // get information from meta fields
            $expected += $this->metaFieldRuleRepository->getWorkingTimeForDate($user, $i);
        } else {
            // otherwise use hours according approvalWorkdayHistory
            switch ($i->format('N')) {
                case (1):
                    $expected += $workdayHistory->getMonday();
                    break;
                case (2):
                    $expected += $workdayHistory->getTuesday();
                    break;
                case (3):
                    $expected += $workdayHistory->getWednesday();
                    break;
                case (4):
                    $expected += $workdayHistory->getThursday();
                    break;
                case (5):
                    $expected += $workdayHistory->getFriday();
                    break;
                case (6):
                    $expected += $workdayHistory->getSaturday();
                    break;
                case (7):
                    $expected += $workdayHistory->getSunday();
                    break;
            }
        }

        return $expected;
    }

    public function findApprovalForUser(User $user, DateTime $begin, DateTime $end): ?Approval
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('ap')
            ->from(Approval::class, 'ap')
            ->andWhere('ap.user = :user')
            ->andWhere('ap.startDate = :begin')
            ->andWhere('ap.endDate = :end')
            ->setParameter('user', $user)
            ->setParameter('begin', $begin->format('Y-m-d'))
            ->setParameter('end', $end->format('Y-m-d'))
            ->orderBy('ap.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findFirstApprovalDateForUser(User $user): ?\DateTime
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('ap.startDate as startDate')
            ->from(Approval::class, 'ap')
            ->andWhere('ap.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ap.startDate')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($result === null) {
            return null;
        }

        return $result['startDate'];
    }

    public function findAllWeek(?array $users): ?array
    {
        if (count($users) == 0) {
            return [];
        }

        $parseToViewArray = $this->getUserApprovals($users);
        $parseToViewArray = $this->addAllNotSubmittedUsers($parseToViewArray, $users);
        if (\count($parseToViewArray) > 5000) {
            $parseToViewArray = \array_slice($parseToViewArray, 0, 5000);
        }

        $result = $parseToViewArray ? $this->sort($parseToViewArray) : [];

        return $this->getNewestPerUser($result);
    }

    public function findAllWeekForUser(User $user, $startDate): ?array
    {
        $parseToViewArray = $this->getUserApprovals([$user], $startDate);
        $result = $parseToViewArray ? $this->sort($parseToViewArray) : [];
        $newestPerUser = $this->getNewestPerUser($result);
        $noUnsubmitted = array_filter(
            $newestPerUser,
            function ($val) {
                return $val['status'] !== 'not_submitted';
            }
        );

        return $this->addYearlyOvertimeToAllWeek($noUnsubmitted, $user);
    }

    private function addYearlyOvertimeToAllWeek(?array $allWeekArray, User $user): ?array
    {
        $sumOvertime = 0;
        $currentYear = '0';
        for ($i = 0; $i < \count($allWeekArray); $i++) {
            $entryYear = substr($allWeekArray[$i]['endDate'], 0, 4);
            if ($currentYear != $entryYear) {
                $endDate = new \DateTime($allWeekArray[$i]['endDate']);
                $overtimeYearly = $this->getExpectedActualDurationsForYear($user, $endDate);
                $sumOvertime = $overtimeYearly['overtime'];
                $allWeekArray[$i]['overtimeYearly'] = $sumOvertime;
                $allWeekArray[$i]['manualAdoption'] = $overtimeYearly['manualAdoption'];
            } else {
                $sumOvertime = $sumOvertime + $allWeekArray[$i]['actualDuration'] - $allWeekArray[$i]['expectedDuration'];
                $allWeekArray[$i]['overtimeYearly'] = $sumOvertime;
            }
        }

        return $allWeekArray;
    }

    private function deleteHistoryFromArray(array $array): array
    {
        $toReturn = [];
        $tmpElement = $array[0];
        foreach ($array as $element) {
            if ($tmpElement['user'] !== $element['user'] || $tmpElement['startDate'] !== $element['startDate']) {
                $toReturn[] = $tmpElement;
            }
            $tmpElement = $element;
        }
        $toReturn[] = $tmpElement;

        return $toReturn;
    }

    private function parseHistoryToOneElement($approvedList): void
    {
        array_map(function (Approval $item) {
            return $this->getLastHistory($item);
        }, $approvedList);
    }

    private function parseToViewArray($approvedList)
    {
        return array_reduce($approvedList, function ($current, Approval $item) {
            if (!empty($item->getHistory())) {
                $current[] =
                    [
                        'userId' => $item->getUser()->getId(),
                        'startDate' => $item->getStartDate()->format('Y-m-d'),
                        'endDate' => $item->getEndDate()->format('Y-m-d'),
                        'expectedDuration' => $item->getExpectedDuration(),
                        'actualDuration' => $item->getActualDuration(),
                        'user' => $item->getUser()->getDisplayName(),
                        'week' => $this->formatting->parseDate(clone $item->getStartDate()),
                        'status' => $item->getHistory()[0]->getStatus()->getName()
                    ];
            }

            return $current;
        }, []);
    }

    public function getWeeks(User $user): array
    {
        $approvedWeeks = $this->getApprovedWeeks($user);

        $weeks = [];
        $freeDays = $this->settingsTool->getConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS);

        $firstDayWorkQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('t')
            ->from(Timesheet::class, 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->orderBy('t.begin', 'ASC')
            ->setMaxResults(1);
        if (!empty($freeDays)) {
            $firstDayWorkQuery = $firstDayWorkQuery
                ->join('t.project', 'p')
                ->join('p.customer', 'c')
                ->andWhere('c.id != :customerId')
                ->setParameter('customerId', $freeDays);
        }
        $firstDayWork = $firstDayWorkQuery
            ->getQuery()
            ->getResult();
        $firstDay = $firstDayWork ? $firstDayWork[0]->getBegin() : new DateTime('today');

        $approval_workflow_start = $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_WORKFLOW_START);
        if ($approval_workflow_start == '') {
            $approval_workflow_start = '0000-01-01';
        }
        $approval_ws_start_week = (new DateTime($approval_workflow_start))->modify('-7 day')->format('Y-m-d');

        if ($firstDay->format('D') !== 'Mon') {
            $firstDay = clone new DateTime($firstDay->modify('last monday')->format('Y-m-d H:i:s'));
        }
        while ($firstDay <= new DateTime('today')) {
            if (!\in_array($firstDay, $approvedWeeks) && $firstDay->format('Y-m-d') > $approval_ws_start_week) {
                $weeks[] = (object) [
                    'label' => $this->formatting->parseDate($firstDay),
                    'value' => (clone $firstDay)->format('Y-m-d')
                ];
            }
            $firstDay->modify('next monday');
        }

        array_pop($weeks);

        return $weeks;
    }

    private function getApprovedWeeks(User $user)
    {
        $approval = array_filter(
            $this->findWithHistory($user),
            function (Approval $approval) {
                $history = $approval->getHistory();
                if (!empty($history)) {
                    /** @var ApprovalHistory $approvalHistory */
                    $approvalHistory = $history[\count($history) - 1];

                    return \in_array(
                        $approvalHistory->getStatus()->getName(),
                        [
                            ApprovalStatus::GRANTED,
                            ApprovalStatus::SUBMITTED
                        ]
                    );
                } else {
                    return false;
                }
            }
        );

        return array_reduce(
            $approval,
            function ($array, $value) {
                $array[] = $value->getStartDate();

                return $array;
            },
            []
        );
    }

    private function findWithHistory(User $user)
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('a')
            ->from(Approval::class, 'a')
            ->join('a.history', 'ah')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('ah.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    private function addAllNotSubmittedUsers($parseToViewArray, array $users)
    {
        $usedUsersWeeks = array_map(function ($approve) {
            return $approve['userId'] . '-' . $approve['startDate'];
        }, $parseToViewArray ?: []);
        foreach ($users as $user) {
            $weeks = $this->getWeeks($user);
            foreach ($weeks as $week) {
                if (!\in_array($user->getId() . '-' . $week->value, $usedUsersWeeks)) {
                    $parseToViewArray[] =
                        [
                            'userId' => $user->getId(),
                            'startDate' => $week->value,
                            'user' => $user->getDisplayName(),
                            'week' => $week->label,
                            'status' => ApprovalStatus::NOT_SUBMITTED
                        ];
                }
            }
        }

        return $parseToViewArray;
    }

    public function findHistoryForUserAndWeek($userId, $week)
    {
        if ($week instanceof DateTime) {
            $weekString = $week->format('Y-m-d');
        } else {
            $weekString = $week;
        }
        
        $em = $this->getEntityManager();

        return $em->createQueryBuilder()
            ->select('ap')
            ->from(Approval::class, 'ap')
            ->join('ap.user', 'u')
            ->join('ap.history', 'ah')
            ->where('ap.startDate = :startDate')
            ->andWhere('u.id = :userId')
            ->setParameter('startDate', $weekString)
            ->setParameter('userId', $userId)
            ->orderBy('ah.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function sort($addNotSubmittedUsers)
    {
        usort($addNotSubmittedUsers, function ($approveA, $approvalB) {
            if ($approveA['startDate'] < $approvalB['startDate']) {
                return -1;
            } elseif ($approveA['startDate'] === $approvalB['startDate']) {
                return strcmp(strtoupper($approveA['user']), strtoupper($approvalB['user']));
            }

            return 1;
        });

        return $addNotSubmittedUsers;
    }

    public function getNewestPerUser(?array $array): ?array
    {
        $arrayToReturn = [];
        if ($array) {
            $tmp_element = $array[0];
            foreach ($array as $value) {
                if ($tmp_element['user'] !== $value['user']) {
                    $arrayToReturn[] = $tmp_element;
                }
                if ($tmp_element['user'] === $value['user'] && $tmp_element['startDate'] !== $value['startDate']) {
                    $arrayToReturn[] = $tmp_element;
                }
                $tmp_element = $value;
            }
            $arrayToReturn[] = $tmp_element;
        }

        return $arrayToReturn;
    }

    /* returns an array of approval IDs */
    public function findAllLaterApprovals(string $approveId)
    {
        $originApproval = $this->find($approveId);
        $user = $originApproval->getUser();
        $userId = $originApproval->getUser()->getId();
        $date = $originApproval->getEndDate();

        $allRows = $this->findAllWeek([$user]);
        $allRows = $this->filterWeeksApprovedOrSubmitted($allRows);
        $allRows = $this->filterWeeksLaterThan($allRows, $date->format('Y-m-d'));

        $result = [];
        foreach ($allRows as $week) {
            $tmp = $this->findHistoryForUserAndWeek($userId, $week['startDate']);
            $approvalId = end($tmp)->getId();
            if ($approvalId) {
                $result[] = $approvalId;
            }
        }

        return $result;
    }

    public function findCurrentWeekToApprove(array $users, User $currentUser): int
    {
        $usersId = array_map(function ($user) {
            return $user->getId();
        }, $users);
        $em = $this->getEntityManager()->createQueryBuilder();
        $expr = $em->expr();
        $approvedList = $em
            ->select('ap', 'ah', 'ast')
            ->from(Approval::class, 'ap')
            ->join('ap.user', 'u')
            ->join('ap.history', 'ah')
            ->leftJoin('ah.status', 'ast')
            ->andWhere($expr->in('u.id', ':users'))
            ->setParameter('users', $usersId)
            ->orderBy('ap.startDate', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var array<Approval> $array_filter */
        $array_filter = array_filter($approvedList, function (Approval $approval) {
            $history = $approval->getHistory();
            $history = array_pop($history);

            return $history instanceof ApprovalHistory && $history->getStatus()->getName() === ApprovalStatus::SUBMITTED;
        });

        $toReturn = [];
        foreach ($array_filter as $approval) {
            if (!($approval->getUser()->hasTeamleadRole() && $approval->getUser()->getId() === $currentUser->getId())) {
                $toReturn[] = $approval;
            }
        }

        return \count($toReturn);
    }

    public function areAllUsersApproved($date, $users): bool
    {
        $users = array_reduce($users, function ($current, User $user) {
            $current[] = $user->getUsername();

            return $current;
        });
        $month = (new DateTime($date))->modify('first day of this month');
        $endDay = (new DateTime($date))->modify('last day of this month');
        if ($month->format('N') !== '1') {
            $month->modify('previous monday');
        }

        while ($month <= $endDay) {
            $pastRows = $this->getWeekUserList($month);
            if (!empty(array_diff($users, array_column($pastRows, 'user')))) {
                return false;
            }
            $month->modify('next monday');
        }

        return true;
    }

    public function filterPastWeeksNotApproved($parseToViewArray): array
    {
        return array_reduce(
            $parseToViewArray,
            function ($response, $approve, $initial = []) {
                if (
                    \in_array(
                        $approve['status'],
                        [
                            ApprovalStatus::SUBMITTED,
                            ApprovalStatus::NOT_SUBMITTED,
                            ApprovalStatus::DENIED
                        ]
                    )
                ) {
                    $response[] = $approve;
                }

                return $response;
            },
            []
        );
    }

    public function filterWeeksNotSubmitted($parseToViewArray): array
    {
        return array_reduce(
            $parseToViewArray,
            function ($response, $approve) {
                if (\in_array($approve['status'], [ApprovalStatus::NOT_SUBMITTED])) {
                    $response[] = $approve;
                }

                return $response;
            },
            []
        );
    }

    public function filterWeeksApprovedOrSubmitted($parseToViewArray): array
    {
        return array_reduce(
            $parseToViewArray,
            function ($response, $approve) {
                if (\in_array($approve['status'], [ApprovalStatus::APPROVED, ApprovalStatus::SUBMITTED])) {
                    $response[] = $approve;
                }

                return $response;
            },
            []
        );
    }

    public function filterWeeksLaterThan($rowArray, $dateString): array
    {
        return array_reduce(
            $rowArray,
            function ($response, $item) use ($dateString) {
                if ($item['startDate'] > $dateString) {
                    $response[] = $item;
                }

                return $response;
            },
            []
        );
    }

    private function getWeekUserList($month): array
    {
        $week = $this->findBy(['startDate' => $month], ['startDate' => 'ASC', 'user' => 'ASC']);
        $this->parseHistoryToOneElement($week);
        $parseToViewArray = $this->parseToViewArray($week);

        return array_filter($this->getNewestPerUser($parseToViewArray), function ($user) {
            return $user['status'] === ApprovalStatus::APPROVED;
        });
    }

    private function generateURLtoApprovals(array $approvals): array
    {
        foreach ($approvals as &$approval) {
            $approval['url'] = $this->getUrl($approval['userId'], $approval['startDate']);
        }

        return $approvals;
    }

    public function getUrl(string $userId, string $date): string
    {
        $url = $this->settingsTool->getConfiguration(ConfigEnum::META_FIELD_EMAIL_LINK_URL);
        $path = $this->urlGenerator->generate('approval_bundle_report', [
            'user' => $userId,
            'date' => $date
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        return rtrim($url, '/') . $path;
    }

    public function getAllNotSubmittedApprovals(array $users): array
    {
        if (count($users) == 0) {
            return [];
        }

        $allRows = $this->findAllWeek($users);
        $allRows = $this->filterWeeksNotSubmitted($allRows);
        $approvals = $this->generateURLtoApprovals($allRows);

        return array_reduce(
            $approvals,
            function ($result, $approval) {
                $result[$approval['userId']][] = $approval;

                return $result;
            },
            []
        );
    }

    public function getUserApprovals(?array $users, $startDate = null)
    {
        if (count($users) == 0) {
            return [];
        }

        $usersId = array_map(function ($user) {
            return $user->getId();
        }, $users);

        $approval_workflow_start = $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_WORKFLOW_START);
        if ($approval_workflow_start == '') {
            $approval_workflow_start = '0000-01-01';
        } else {
            $approval_workflow_start = (new DateTime($approval_workflow_start))->modify('-7 day')->format('Y-m-d');
        }

        if ($startDate !== null) {
            $start = $startDate;
        } else {
            $start = $approval_workflow_start;
        }

        $em = $this->getEntityManager();
        $approvedList = $em->createQueryBuilder()
            ->select('ap')
            ->from(Approval::class, 'ap')
            ->join('ap.user', 'u')
            ->andWhere($em->getExpressionBuilder()->in('u.id', $usersId))
            ->andWhere('ap.startDate >= :begin')
            ->setParameter('begin', $start)
            ->orderBy('ap.startDate', 'ASC')
            ->addOrderBy('u.username', 'ASC')
            ->addOrderBy('ap.creationDate', 'ASC')
            ->groupBy('ap')
            ->getQuery()
            ->getResult();

        $this->parseHistoryToOneElement($approvedList);
        $parseToViewArray = $this->parseToViewArray($approvedList);
        if ($parseToViewArray) {
            $parseToViewArray = $this->deleteHistoryFromArray($parseToViewArray);
        }

        return $parseToViewArray;
    }

    private function getLastHistory(Approval $item)
    {
        $history = $item->getHistory();
        if (!empty($history)) {
            $item->setHistory([$history[\count($history) - 1]]);
        } else {
            $item->setHistory([]);
        }

        return $item;
    }

    public function checkLastStatus(DateTime $startDate, $endDate, User $user, string $seededStatus, Approval $approval): ?Approval
    {
        $oldApproval = $this->findOneBy(['startDate' => $startDate, 'endDate' => $endDate, 'user' => $user], ['id' => 'DESC']);
        if ($oldApproval) {
            $oldApproval = $this->getLastHistory($oldApproval);
            if ($oldApproval->hasHistory() && $oldApproval->getHistory()[0]->getStatus()->getName() !== $seededStatus) {
                return null;
            }
        }

        return $approval;
    }

    public function getNextApproveWeek(User $user): ?string
    {
        $allRows = $this->findAllWeek([$user]);
        $allNotSubmittedRows = $this->filterWeeksNotSubmitted($allRows);

        // When there are past/current not submitted rows, return that date
        if (!empty($allNotSubmittedRows)) {
            return $allNotSubmittedRows[0]['startDate'];
        }

        // If there are no initial values, return nothing
        if (empty($allRows)) {
            return null;
        }

        // Otherwise, when there are $allRows, get the one which would be next (located in the future)
        $prevWeekDay = end($allRows)['startDate'];
        $prev = strtotime($prevWeekDay . ' + 7 days');
        if ($prev === false) {
            throw new \RuntimeException('Could not calculate next week');
        }

        return date('Y-m-d', $prev);
    }

    public function updateExpectedActualDurationForUser(User $user)
    {
        $approvals = $this->findBy(['user' => $user]);

        foreach ($approvals as $approval) {
            $start = $approval->getStartDate();
            $end = $approval->getEndDate();
            date_time_set($end, 23, 59, 59);
            $expected = $approval->getExpectedDuration();
            $actual = $approval->getActualDuration();
            $stats = $this->getExpectedActualDurations($user, $start, $end);

            if ($stats['actualDuration'] !== $actual) {
                $approval->setActualDuration($stats['actualDuration']);
                $this->getEntityManager()->persist($approval);
            }
            if ($stats['expectedDuration'] !== $expected) {
                $approval->setExpectedDuration($stats['expectedDuration']);
                $this->getEntityManager()->persist($approval);
            }
            $this->getEntityManager()->flush();
        }
    }
}
