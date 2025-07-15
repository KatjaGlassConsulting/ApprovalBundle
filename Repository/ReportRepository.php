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
use App\Model\DailyStatistic;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Timesheet|null find($id, $lockMode = null, $lockVersion = null)
 * @method Timesheet|null findOneBy(array $criteria, array $orderBy = null)
 * @method Timesheet[] findAll()
 * @method Timesheet[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ReportRepository extends ServiceEntityRepository
{
    /**
     * @var ProjectRepository
     */
    private $projectRepository;
    /**
     * @var ActivityRepository
     */
    private $activityRepository;

    public function __construct(
        ManagerRegistry $registry,
        ProjectRepository $projectRepository,
        ActivityRepository $activityRepository
    ) {
        parent::__construct($registry, Timesheet::class);
        $this->projectRepository = $projectRepository;
        $this->activityRepository = $activityRepository;
    }

    public function getDailyStatistic(User $user, \DateTime $begin, \DateTime $end): array
    {
        $result = $this->getTimesheetForApprovalWeeks($begin, $end, $user);

        $report = [];
        foreach ($result as $value) {
            $title = $this->projectRepository->find($value['project'])->getCustomer()->getName() . ' - ' .
                $this->projectRepository->find($value['project'])->getName() . ' - ' .
                $this->activityRepository->find($value['activity'])->getName();
            if (!isset($report[$title])) {
                $report = $this->newActivity($value, $begin, $end, $user, $report, $title);
            } else {
                $report = $this->updateActivity($value, $report, $title, $begin, $end, $user);
            }
            ksort($report[$title]['details']);
        }

        return $report;
    }

    private function getTimesheetForApprovalWeeks(\DateTime $begin, \DateTime $end, User $user)
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->addSelect('COALESCE(SUM(t.duration), 0) as duration')
            ->addSelect('IDENTITY(t.user) as user')
            ->addSelect('IDENTITY(t.project) as project')
            ->addSelect('IDENTITY(t.activity) as activity')
            ->addSelect('t.description as description')
            ->addSelect('DATE(t.date) as date')
            ->from(Timesheet::class, 't')
            ->where($qb->expr()->isNotNull('t.end'))
            ->andWhere($qb->expr()->between('t.begin', ':begin', ':end'))
            ->andWhere($qb->expr()->in('t.user', ':user'))
            ->setParameter('begin', $begin)
            ->setParameter('end', $end)
            ->setParameter('user', $user)
            ->orderBy('date')
            ->addOrderBy('project')
            ->addOrderBy('activity')
            ->groupBy('date')
            ->addGroupBy('project')
            ->addGroupBy('activity')
            ->addGroupBy('user')
            ->addGroupBy('description');

        $result = $qb->getQuery()->getResult();

        return $result;
    }

    public function getActualWorkingDurationStatistic(User $user, \DateTime $begin, \DateTime $end): int
    {
        if ($end->format('H:i:s') == '00:00:00') {
            $maxEndTime = clone $end;
            $maxEndTime->setTime(23, 59, 59);

            return $this->getActualWorkingDuration($begin, $maxEndTime, $user);
        } else {
            return $this->getActualWorkingDuration($begin, $end, $user);
        }
    }

    private function getActualWorkingDuration(\DateTime $begin, \DateTime $end, User $user): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();
        $qb
            ->select('COALESCE(SUM(t.duration), 0) as duration')
            ->from(Timesheet::class, 't')
            ->where($qb->expr()->isNotNull('t.end'))
            ->andWhere($qb->expr()->between('t.begin', ':begin', ':end'))
            ->andWhere($qb->expr()->in('t.user', ':user'))
            ->setParameter('begin', $begin)
            ->setParameter('end', $end)
            ->setParameter('user', $user)
            ->setMaxResults(1);

        $result = $qb->getQuery()->getOneOrNullResult();

        return (int) $result['duration'];
    }

    private function generateDailyStatistics(\DateTime $begin, \DateTime $end, User $user, $value, array $array): array
    {
        $days = new DailyStatistic($begin, $end, $user);
        $day = $days->getDayByReportDate($value['date']);
        
        // If the day is not found - time going over the week (sunday->monday) and is by chance documented as on next day - use the previous day (sunday)
        if ($day === null) {
            $previousDay = (new \DateTime($value['date']))->modify('-1 day')->format('Y-m-d');
            $day = $days->getDayByReportDate($previousDay);            
        }

        $day->setTotalDuration($day->getTotalDuration() + (int) $value['duration']);
        $array['days'] = $days;

        return $array;
    }

    private function updateExistingDescription(array $report, string $title, $value): array
    {
        $valueDays = $report[$title]['details'][$value['description']]['days'];
        $valueDay = $valueDays->getDayByReportDate($value['date']);

        // If the day is not found - time going over the week (sunday->monday) and is by chance documented as on next day - use the previous day (sunday)
        if ($valueDay === null) {
            $previousDay = (new \DateTime($value['date']))->modify('-1 day')->format('Y-m-d');
            $valueDay = $valueDays->getDayByReportDate($previousDay);            
        }

        $valueDay->setTotalDuration($valueDay->getTotalDuration() + (int) $value['duration']);
        $report[$title]['details'][$value['description']]['days'] = $valueDays;
        $report[$title]['details'][$value['description']]['duration'] += $value['duration'];

        return $report;
    }

    private function creatNewDescription(\DateTime $begin, \DateTime $end, User $user, $value, $report, string $title): array
    {
        $valueDays = new DailyStatistic($begin, $end, $user);
        $valueDay = $valueDays->getDayByReportDate($value['date']);
        
        // If the day is not found - time going over the week (sunday->monday) and is by chance documented as on next day - use the previous day (sunday)
        if ($valueDay === null) {
            $previousDay = (new \DateTime($value['date']))->modify('-1 day')->format('Y-m-d');
            $valueDay = $valueDays->getDayByReportDate($previousDay);            
        }

        $valueDay->setTotalDuration($valueDay->getTotalDuration() + (int) $value['duration']);
        $value['days'] = $valueDays;
        $report[$title]['details'][$value['description']] = $value;

        return $report;
    }

    private function newActivity($value, \DateTime $begin, \DateTime $end, User $user, array $report, string $title): array
    {
        $record = [];
        $record['duration'] = $value['duration'];

        $record = $this->generateDailyStatistics($begin, $end, $user, $value, $record);
        $value = $this->generateDailyStatistics($begin, $end, $user, $value, $value);

        $record['details'] = [];
        $record['details'][$value['description']] = $value;

        $report[$title] = $record;

        return $report;
    }

    private function updateActivity($value, $report, string $title, \DateTime $begin, \DateTime $end, User $user): array
    {
        $report[$title]['duration'] += $value['duration'];

        $days = $report[$title]['days'];
        $day = $days->getDayByReportDate($value['date']);

        // If the day is not found - time going over the week (sunday->monday) and is by chance documented as on next day - use the previous day (sunday)
        if ($day === null) {
            $previousDay = (new \DateTime($value['date']))->modify('-1 day')->format('Y-m-d');
            $day = $days->getDayByReportDate($previousDay);            
        }
        
        $day->setTotalDuration($day->getTotalDuration() + (int) $value['duration']);

        if (isset($report[$title]['details'][$value['description']])) {
            $report = $this->updateExistingDescription($report, $title, $value);
        } else {
            $report = $this->creatNewDescription($begin, $end, $user, $value, $report, $title);
        }
        $report[$title]['days'] = $days;

        return $report;
    }
}
