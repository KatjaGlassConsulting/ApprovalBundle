<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use DateInterval;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
use App\Repository\TimesheetRepository as CoreTimesheetRepository;
use App\Repository\CustomerRepository;
use App\Entity\Timesheet;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;


class ApprovalTimesheetRepository extends ServiceEntityRepository
{
    /**
     * @var CoreTimesheetRepository
     */
    private $timesheetRepository;

    /**
     * @var CustomerRepository
     */
    private $customerRepository;

    /**
     * @var SettingsTool
     */
    private $settingsTool;

    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;

    /**
     * @param CoreTimesheetRepository $timesheetRepository
     */
    public function __construct(
        ManagerRegistry $registry,
        CoreTimesheetRepository $timesheetRepository,
        CustomerRepository $customerRepository,
        SettingsTool $settingsTool,
        ApprovalRepository $approvalRepository
    ) {
        parent::__construct($registry, Approval::class);
        $this->timesheetRepository = $timesheetRepository;
        $this->customerRepository = $customerRepository;
        $this->settingsTool = $settingsTool;
        $this->approvalRepository = $approvalRepository;
    }

    public function updateDaysOff(User $user)
    {
        $activityHolidays = $this->settingsTool->getConfiguration(ConfigEnum::ACTIVITY_FOR_HOLIDAYS);
        $activityVacations = $this->settingsTool->getConfiguration(ConfigEnum::ACTIVITY_FOR_VACATIONS);
        
        $activityIds = [];
        if ($activityHolidays !== null) {
            $activityIds[] = $activityHolidays;
        }
        if ($activityVacations !== null) {
            $activityIds[] = $activityVacations;
        }

        $currentYear = date('Y'); 
        $start = "01-01-" . strval($currentYear - 1);

        if (!empty($activityIds)){
          $freeDaysTimesheetsQuery = $this->getEntityManager()->createQueryBuilder()
                ->select('t')
                ->from(Timesheet::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->andWhere('t.activity IN (:activityIds)')
                ->andWhere('t.begin >= :begin')
                ->setParameter('begin', $start)
                ->setParameter('activityIds', $activityIds);        
            $freeDaysTimesheets = $freeDaysTimesheetsQuery->getQuery()->getResult();

            foreach ($freeDaysTimesheets as $timesheet) {
                $timeSheetDuration = $timesheet->getDuration();
                $expectedCurrent = 0;
                $expectedCurrent = $this->approvalRepository->getExpectTimeForDate($timesheet->getBegin(), $user, $expectedCurrent);
                if ($timeSheetDuration != $expectedCurrent){
                    // if duration differs, then update end and duration
                    $newEnd = clone $timesheet->getBegin();
                    $newEnd->add(new DateInterval('PT' . $expectedCurrent. 'S'));                
                    $timesheet->setEnd(null);   // reset rates if available (this should not be the case)
                    $timesheet->setEnd($newEnd);
                    $timesheet->setDuration($expectedCurrent);
                    $this->getEntityManager()->persist($timesheet);
              }
          }

          $this->getEntityManager()->flush();
        }

        
    }
}
