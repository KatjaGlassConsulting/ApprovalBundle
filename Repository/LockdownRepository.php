<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use App\Entity\User;
use App\Entity\UserPreference;
use DateTime;
use DateTimeZone;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;
use KimaiPlugin\ApprovalBundle\Entity\Approval;

class LockdownRepository extends ServiceEntityRepository
{
    private const LOCKDOWN_PERIOD_START = 'lockdown_period_start';
    private const LOCKDOWN_PERIOD_END = 'lockdown_period_end';
    private const LOCKDOWN_PERIOD_TIMEZONE = 'lockdown_period_timezone';
    private const LOCKDOWN_GRACE_PERIOD = 'lockdown_grace_period';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Approval::class);
    }

    /**
     * @throws Exception
     */
    public function updateLockWeek(?Approval $approval, ApprovalRepository $approvalRepository)
    {
        if ($approval) {
            $user = $approval->getUser();

            $allWeeks = $approvalRepository->findAllWeek([$user]);
            $endDate = $this->getOldestNotSubmittedDate($allWeeks, $user);
            $endDate = $this->getOldestSubmittedApprovalFromTeam($user, $approvalRepository, $endDate);

            $graceDate = $this->getGraceDate(clone $endDate, new DateTimeZone($user->getTimezone()));
            $endDate->modify('midnight')->modify('+1 day')->modify('-1 second');
            $this->updateLockPreference($user, $endDate, $graceDate);
        }
    }

    private function updatePreference(User $user, string $preferenceName, string $value): void
    {
        $preference = $user->getPreference($preferenceName);
        if ($preference) {
            $preference->setValue($value);
        } else {
            $preference = new UserPreference($preferenceName, $value);
            $preference->setUser($user);
        }
        $this->getEntityManager()->persist($preference);
        $this->getEntityManager()->flush();
    }

    /**
     * @throws Exception
     */
    private function getOldestNotSubmittedDate(array $allWeeks, User $user): DateTime
    {
        $timezone = new DateTimeZone($user->getTimezone());

        foreach ($allWeeks as $week) {
            if ($week['status'] === 'not_submitted') {
                $startDate = $week['startDate'];

                return (new DateTime($startDate, $timezone))->modify('previous sunday');
            }
        }

        if ($allWeeks) {
            $startDate = end($allWeeks)['startDate'];
        } else {
            $startDate = (new DateTime())->format('Y-m-d');
        }

        return (new DateTime($startDate, $timezone))->modify('next sunday');
    }

    private function updateLockPreference(User $user, $endDate, $graceDate): void
    {
        $this->updatePreference($user, self::LOCKDOWN_PERIOD_START, '0000-01-01 00:00:01');
        $this->updatePreference($user, self::LOCKDOWN_PERIOD_END, $endDate->format('Y-m-d H:i:s'));
        $this->updatePreference($user, self::LOCKDOWN_PERIOD_TIMEZONE, $user->getTimezone());
        $this->updatePreference($user, self::LOCKDOWN_GRACE_PERIOD, $graceDate->format('Y-m-d H:i:s'));
    }

    private function getOldestSubmittedApprovalFromTeam(User $user, ApprovalRepository $approvalRepository, $endDate): DateTime
    {
        foreach ($user->getTeams() as $team) {
            if (\in_array($user, $team->getTeamleads())) {
                foreach ($team->getUsers() as $teamUser) {
                    $allWeeks = $approvalRepository->getUserApprovals([$teamUser]);
                    $userLastApproval = $this->getOldestNotSubmittedDate($allWeeks, $teamUser);
                    $endDate = $userLastApproval < $endDate ? $userLastApproval : $endDate;
                }
            }
        }

        return $endDate;
    }

    /**
     * @param User $user
     * @param ApprovalRepository $approvalRepository
     * @return void
     * @throws Exception
     * @phpstan-ignore-next-line
     */
    private function updateTeamleadersLock(User $user, ApprovalRepository $approvalRepository): void
    {
        foreach ($user->getTeams() as $team) {
            foreach ($team->getTeamleads() as $teamLeader) {
                $weeks = $approvalRepository->findAllWeek([$teamLeader]);
                $endDate = $this->getOldestNotSubmittedDate($weeks, $teamLeader);
                $endDate = $this->getOldestSubmittedApprovalFromTeam($teamLeader, $approvalRepository, $endDate);

                $graceDate = $this->getGraceDate(clone $endDate, new DateTimeZone($user->getTimezone()));
                $endDate->modify('midnight')->modify('+1 day')->modify('-1 second');
                $this->updateLockPreference($teamLeader, $endDate, $graceDate);
            }
        }
    }

    private function getGraceDate(DateTime $endDate, DateTimeZone $dateTimeZone): DateTime
    {
        $now = new DateTime('now', $dateTimeZone);
        if ($endDate > $now) {
            $endDate = $now;
        } else {
            $endDate->modify('midnight')->modify('+1 day')->modify('-1 second');
        }

        return $endDate;
    }
}
