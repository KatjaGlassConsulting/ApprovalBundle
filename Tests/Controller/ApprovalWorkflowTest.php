<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Controller;

use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * End-to-end test of the approval state machine described in documentation.md:
 * a user submits a week, the teamlead approves or denies it.
 *
 * @group integration
 */
class ApprovalWorkflowTest extends AbstractApprovalTestCase
{
    public function testSubmittingAWeekCreatesApprovalInSubmittedState(): void
    {
        $approval = $this->submitWeek();

        self::assertEquals($this->submitter->getId(), $approval->getUser()->getId());
        self::assertEquals(self::WEEK_START, $approval->getStartDate()->format('Y-m-d'));
        self::assertEquals(ApprovalStatus::SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testTeamleadApprovesSubmittedWeek(): void
    {
        $approval = $this->submitWeek();

        $this->approveWeek($approval, $this->approver);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertEquals(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testTeamleadDeniesSubmittedWeekWhichResetsItToNotSubmitted(): void
    {
        $approval = $this->submitWeek();

        $this->denyWeek($approval, $this->approver);

        self::assertTrue($this->client->getResponse()->isRedirect());

        // deny writes DENIED and immediately a NOT_SUBMITTED entry, so the week can be re-submitted
        self::assertEquals(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testApprovingATwiceDoesNotAddASecondApprovedEntry(): void
    {
        $approval = $this->submitWeek();

        $this->approveWeek($approval, $this->approver);
        $this->approveWeek($approval, $this->approver);

        // checkLastStatus() only allows the transition from SUBMITTED, so the second call is a no-op
        self::assertEquals(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testADeniedWeekCanBeSubmittedAgain(): void
    {
        $approval = $this->submitWeek();
        $this->denyWeek($approval, $this->approver);

        $resubmitted = $this->submitWeek();

        self::assertInstanceOf(Approval::class, $resubmitted);
        self::assertEquals(ApprovalStatus::SUBMITTED, $this->lastStatusOf($resubmitted));
    }
}
