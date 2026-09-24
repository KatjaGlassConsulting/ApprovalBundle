<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Controller;

use App\Entity\User;
use App\Entity\UserPreference;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * documentation.md, section "Lockdown Process (required LockdownBundle)":
 *
 *   "When a user sets a week to approval, then the end time of that week is used as lockdown
 *    date for this user."
 *
 *   "When a week which was send to approval is denied (by teamlead or admin) or undone (by
 *    admin), then this week is opened, the lockdown date is set to the end of the previous
 *    week. As this would allow any following week to be updated as well, all weeks that follow
 *    that denied/re-opened week are also reset and need a new approval."
 *
 * The lockdown date is stored as the Kimai user preference "lockdown_period_end", which the
 * LockdownPeriodPerUser validator of the core evaluates.
 *
 * @group integration
 */
class ApprovalLockdownTest extends AbstractApprovalTestCase
{
    private const LOCKDOWN_PERIOD_END = 'lockdown_period_end';

    /**
     * LockdownRepository persists the preference without adding it to the in-memory user,
     * so it has to be read back from the database.
     */
    private function lockdownEndOf(User $user): ?string
    {
        $preference = $this->getEntityManager()
            ->getRepository(UserPreference::class)
            ->findOneBy(['user' => $user, 'name' => self::LOCKDOWN_PERIOD_END]);

        return $preference?->getValue();
    }

    public function testSubmittingAWeekLocksTimesheetsUntilTheEndOfThatWeek(): void
    {
        $this->submitWeek();

        // the submitted week runs from Monday 2024-08-26 to Sunday 2024-09-01
        self::assertSame('2024-09-01 23:59:59', $this->lockdownEndOf($this->submitter));
    }

    public function testApprovingKeepsTheLockdownAtTheEndOfTheApprovedWeek(): void
    {
        $approval = $this->submitWeek();

        $this->approveWeek($approval, $this->approver);

        self::assertSame('2024-09-01 23:59:59', $this->lockdownEndOf($this->submitter));
    }

    public function testDenyingAWeekMovesTheLockdownBackToTheEndOfThePreviousWeek(): void
    {
        $approval = $this->submitWeek();
        self::assertSame('2024-09-01 23:59:59', $this->lockdownEndOf($this->submitter));

        $this->denyWeek($approval, $this->approver);

        // the week is open again, so the lock ends with the previous Sunday
        self::assertSame('2024-08-25 23:59:59', $this->lockdownEndOf($this->submitter));
    }

    public function testUndoingAnApprovalMovesTheLockdownBackToTheEndOfThePreviousWeek(): void
    {
        $approval = $this->submitWeek();
        $this->approveWeek($approval, $this->approver);

        $this->undoApproval($approval, $this->admin);

        self::assertSame('2024-08-25 23:59:59', $this->lockdownEndOf($this->submitter));
    }

    public function testDenyingAWeekAlsoResetsAllFollowingWeeks(): void
    {
        $week = $this->submitWeek();
        $followingWeek = $this->submitWeek(self::NEXT_WEEK_START);

        self::assertSame(ApprovalStatus::SUBMITTED, $this->lastStatusOf($followingWeek));

        $this->denyWeek($week, $this->approver);

        self::assertSame(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($week));
        self::assertSame(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($followingWeek));
    }

    public function testUndoingAnApprovalAlsoResetsAllFollowingWeeks(): void
    {
        $week = $this->submitWeek();
        $followingWeek = $this->submitWeek(self::NEXT_WEEK_START);
        $this->approveWeek($week, $this->approver);

        $this->undoApproval($week, $this->admin);

        self::assertSame(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($week));
        self::assertSame(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($followingWeek));
    }
}
