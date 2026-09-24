<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Controller;

use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use Symfony\Component\HttpFoundation\Response;

/**
 * documentation.md splits the plugin into three roles:
 *
 *   "Users - Submit for Approval"       -> may submit their own weeks, nothing else
 *   "Teamleads - ... approve team members weeks"
 *   "Admins - Overview & Overrule"      -> "the admin is able to reset an already approved week"
 *
 * The approve/deny routes carry #[IsGranted] expressions for "view_team_approval" or
 * "view_all_approval", the undo route requires "view_all_approval"; ApprovalExtension::prepend()
 * grants the first to ROLE_TEAMLEAD and both to ROLE_SUPER_ADMIN.
 *
 * @group integration
 */
class ApprovalRolesTest extends AbstractApprovalTestCase
{
    public function testAUserMaySubmitTheirOwnWeek(): void
    {
        $approval = $this->submitWeek();

        self::assertSame(ApprovalStatus::SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testAUserMustNotApproveTheirOwnWeek(): void
    {
        $approval = $this->submitWeek();

        $this->approveWeek($approval, $this->submitter);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame(ApprovalStatus::SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testAUserMustNotDenyAWeek(): void
    {
        $approval = $this->submitWeek();

        $this->denyWeek($approval, $this->submitter);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame(ApprovalStatus::SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testAUserMustNotUndoAnApproval(): void
    {
        $approval = $this->submitWeek();
        $this->approveWeek($approval, $this->approver);

        $this->undoApproval($approval, $this->submitter);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testATeamleadMayApproveTheWeekOfATeamMember(): void
    {
        $approval = $this->submitWeek();

        $this->approveWeek($approval, $this->approver);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testATeamleadMustNotUndoAnApproval(): void
    {
        // documentation.md: "Once an approval is accepted, the teamlead is not able to "undo" that acceptance."
        $approval = $this->submitWeek();
        $this->approveWeek($approval, $this->approver);

        $this->undoApproval($approval, $this->approver);

        self::assertSame(Response::HTTP_FORBIDDEN, $this->client->getResponse()->getStatusCode());
        self::assertSame(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testAnAdminMayResetAnAlreadyApprovedWeek(): void
    {
        $approval = $this->submitWeek();
        $this->approveWeek($approval, $this->approver);
        self::assertSame(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));

        $this->undoApproval($approval, $this->admin);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertSame(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testResettingIsOnlyPossibleForAnApprovedWeek(): void
    {
        // still SUBMITTED - checkLastStatus() only allows the transition away from APPROVED
        $approval = $this->submitWeek();

        $this->undoApproval($approval, $this->admin);

        self::assertSame(ApprovalStatus::SUBMITTED, $this->lastStatusOf($approval));
    }
}
