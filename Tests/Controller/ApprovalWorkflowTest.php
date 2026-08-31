<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Controller;

use App\Entity\User;
use App\Tests\Controller\AbstractControllerBaseTestCase;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Tests\DataFixtures\ApprovalUserFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * End-to-end test of the approval state machine, driven through the real HTTP routes:
 *
 *   submitter  GET /approval/add_to_approve   -> SUBMITTED
 *   approver   GET /approval/approve/{id}     -> APPROVED
 *   approver   GET /approval/denied/{id}      -> DENIED, then NOT_SUBMITTED
 *
 * The kernel may only be booted once per test, so a single client is created up front and
 * the identity is switched with loginUser() instead of creating a second client.
 *
 * @group integration
 */
class ApprovalWorkflowTest extends AbstractControllerBaseTestCase
{
    private const WEEK_START = '2024-08-26'; // a Monday

    private KernelBrowser $client;
    private User $submitter;
    private User $approver;

    protected function setUp(): void
    {
        parent::setUp();

        // must happen before the container is touched by importFixture()
        $this->client = static::createClient();

        [$this->submitter, $this->approver] = $this->importFixture(new ApprovalUserFixtures());
    }

    private function loginAs(User $user): void
    {
        $this->client->loginUser($user, 'secured_area');
    }

    private function lastStatusOf(Approval $approval): string
    {
        /** @var ApprovalHistoryRepository $historyRepository */
        $historyRepository = $this->getPrivateService(ApprovalHistoryRepository::class);

        return $historyRepository->findLastStatus($approval->getId())->getStatus()->getName();
    }

    private function findApproval(): ?Approval
    {
        /** @var ApprovalRepository $repository */
        $repository = $this->getPrivateService(ApprovalRepository::class);

        return $repository->findApprovalForUser(
            $this->submitter,
            new \DateTime(self::WEEK_START),
            (new \DateTime(self::WEEK_START))->modify('next sunday')
        );
    }

    private function submitWeek(): Approval
    {
        $this->loginAs($this->submitter);
        $this->request($this->client, '/approval/add_to_approve?user=' . $this->submitter->getId() . '&date=' . self::WEEK_START);

        self::assertTrue($this->client->getResponse()->isRedirect(), 'submitting the week should redirect');

        $approval = $this->findApproval();
        self::assertInstanceOf(Approval::class, $approval, 'submitting the week did not create an approval');

        return $approval;
    }

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

        $this->loginAs($this->approver);
        $this->request($this->client, '/approval/approve/' . $approval->getId() . '?user=' . $this->submitter->getId() . '&date=' . self::WEEK_START);

        self::assertTrue($this->client->getResponse()->isRedirect());
        self::assertEquals(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }

    public function testTeamleadDeniesSubmittedWeekWhichResetsItToNotSubmitted(): void
    {
        $approval = $this->submitWeek();

        $this->loginAs($this->approver);
        $this->request($this->client, '/approval/denied/' . $approval->getId() . '?user=' . $this->submitter->getId() . '&date=' . self::WEEK_START . '&input=please+fix+monday');

        self::assertTrue($this->client->getResponse()->isRedirect());

        // deny writes DENIED and immediately a NOT_SUBMITTED entry, so the week can be re-submitted
        self::assertEquals(ApprovalStatus::NOT_SUBMITTED, $this->lastStatusOf($approval));
    }

    public function testApprovingATwiceDoesNotAddASecondApprovedEntry(): void
    {
        $approval = $this->submitWeek();
        $url = '/approval/approve/' . $approval->getId() . '?user=' . $this->submitter->getId() . '&date=' . self::WEEK_START;

        $this->loginAs($this->approver);
        $this->request($this->client, $url);
        $this->request($this->client, $url);

        // checkLastStatus() only allows the transition from SUBMITTED, so the second call is a no-op
        self::assertEquals(ApprovalStatus::APPROVED, $this->lastStatusOf($approval));
    }
}
