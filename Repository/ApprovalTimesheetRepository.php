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
use Symfony\Contracts\Translation\TranslatorInterface;


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
     * @var TranslatorInterface
     */
    private $translator;

    /**
     * @param CoreTimesheetRepository $timesheetRepository
     */
    public function __construct(
        ManagerRegistry $registry,
        CoreTimesheetRepository $timesheetRepository,
        CustomerRepository $customerRepository,
        SettingsTool $settingsTool,
        ApprovalRepository $approvalRepository,
        TranslatorInterface $translator
    ) {
        parent::__construct($registry, Approval::class);
        $this->timesheetRepository = $timesheetRepository;
        $this->customerRepository = $customerRepository;
        $this->settingsTool = $settingsTool;
        $this->approvalRepository = $approvalRepository;
        $this->translator = $translator;
    }

    public function updateDaysOff(User $user): array
    {
        $errors = [];
        $customer = $this->customerRepository->find($this->settingsTool->getConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS));

        $currentYear = date('Y'); 
        $start = "01-01-" . strval($currentYear - 1);

        if ($customer != null){
          $freeDaysTimesheetsQuery = $this->getEntityManager()->createQueryBuilder()
                ->select('t')
                ->from(Timesheet::class, 't')
                ->where('t.user = :user')
                ->setParameter('user', $user)
                ->join('t.project', 'p')
                ->join('p.customer', 'c')
                ->andWhere('c.id = :customerId')
                ->andWhere('t.begin >= :begin')
                ->setParameter('begin', $start)
                ->setParameter('customerId', $customer->getId())
                ->orderBy('t.date', 'ASC');        
            $freeDaysTimesheets = $freeDaysTimesheetsQuery->getQuery()->getResult();

            $lastTimesheetDay = " ";
            foreach ($freeDaysTimesheets as $timesheet) {                
                file_put_contents("C:/temp/blub.txt", "blub " . json_encode($lastTimesheetDay) . " "  . json_encode($timesheet->getBegin()->format('Y-m-d')) . "\n", FILE_APPEND);
                if ($lastTimesheetDay == $timesheet->getBegin()->format('Y-m-d')){                    
                    $errors[] = $user->getDisplayName() . " - " .  $timesheet->getBegin()->format('Y-m-d') . " ". $this->translator->trans("error.multiple_offday_entries");
                    file_put_contents("C:/temp/blub.txt", "issue available error - " . json_encode($errors) . "\n", FILE_APPEND);
                }
                $timeSheetDuration = $timesheet->getDuration();
                $expectedCurrent = 0;
                $expectedCurrent = $this->approvalRepository->getExpectTimeForDate($timesheet->getBegin(), $user, $expectedCurrent);
                // remove tracked hours of non-free-days entries, e.g. people might have worked for time + remaining is sick leave
                $expectedCurrent = $expectedCurrent - $this->approvalRepository->calculateDurationWithoutFreeDays($user, $timesheet->getBegin()->format('Y-m-d'), $customer->getId());
                if ($timeSheetDuration != $expectedCurrent){
                    if ($expectedCurrent < 0){
                        $expectedCurrent = 0;
                    }
                    // if duration differs, then update end and duration
                    $newEnd = clone $timesheet->getBegin();
                    $newEnd->add(new DateInterval('PT' . $expectedCurrent. 'S'));                
                    $timesheet->setEnd(null);   // reset rates if available (this should not be the case)
                    $timesheet->setEnd($newEnd);
                    $timesheet->setDuration($expectedCurrent);
                    $this->getEntityManager()->persist($timesheet);
                }
                $lastTimesheetDay = $timesheet->getBegin()->format('Y-m-d');
            }
            $this->getEntityManager()->flush();
        }

        return $errors;
    }
}
