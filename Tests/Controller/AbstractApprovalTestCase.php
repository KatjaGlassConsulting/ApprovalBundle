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
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Tests\DataFixtures\ApprovalUserFixtures;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Drives the approval state machine through the real HTTP routes:
 *
 *   submitter  GET /approval/add_to_approve    -> SUBMITTED
 *   approver   GET /approval/approve/{id}      -> APPROVED
 *   approver   GET /approval/denied/{id}       -> DENIED, then NOT_SUBMITTED
 *   admin      GET /approval/not_approved/{id} -> NOT_SUBMITTED
 *
 * The kernel may only be booted once per test, so a single client is created up front and
 * the identity is switched with loginUser() instead of creating a second client.
 */
abstract class AbstractApprovalTestCase extends AbstractControllerBaseTestCase
{
    protected const WEEK_START = '2024-08-26';      // a Monday
    protected const NEXT_WEEK_START = '2024-09-02'; // the Monday after

    protected KernelBrowser $client;
    protected User $submitter;
    protected User $approver;
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        // must happen before the container is touched by importFixture()
        $this->client = static::createClient();

        [$this->submitter, $this->approver, $this->admin] = $this->importFixture(new ApprovalUserFixtures());
    }

    protected function loginAs(User $user): void
    {
        $this->client->loginUser($user, 'secured_area');
    }

    protected function approvalRepository(): ApprovalRepository
    {
        /** @var ApprovalRepository $repository */
        $repository = $this->getPrivateService(ApprovalRepository::class);

        return $repository;
    }

    protected function lastStatusOf(Approval $approval): string
    {
        /** @var ApprovalHistoryRepository $historyRepository */
        $historyRepository = $this->getPrivateService(ApprovalHistoryRepository::class);

        return $historyRepository->findLastStatus($approval->getId())->getStatus()->getName();
    }

    protected function findApproval(string $weekStart = self::WEEK_START): ?Approval
    {
        return $this->approvalRepository()->findApprovalForUser(
            $this->submitter,
            new \DateTime($weekStart),
            (new \DateTime($weekStart))->modify('next sunday')
        );
    }

    /**
     * Submits the given week for the submitter and returns the created approval.
     */
    protected function submitWeek(string $weekStart = self::WEEK_START): Approval
    {
        $this->loginAs($this->submitter);
        $this->request($this->client, '/approval/add_to_approve?user=' . $this->submitter->getId() . '&date=' . $weekStart);

        self::assertTrue($this->client->getResponse()->isRedirect(), 'submitting the week should redirect');

        $approval = $this->findApproval($weekStart);
        self::assertInstanceOf(Approval::class, $approval, 'submitting the week did not create an approval');

        return $approval;
    }

    protected function approveWeek(Approval $approval, User $actor, string $weekStart = self::WEEK_START): void
    {
        $this->callApprovalRoute('approve', $approval, $actor, $weekStart);
    }

    protected function denyWeek(Approval $approval, User $actor, string $weekStart = self::WEEK_START): void
    {
        $this->callApprovalRoute('denied', $approval, $actor, $weekStart, '&input=please+fix+monday');
    }

    protected function undoApproval(Approval $approval, User $actor, string $weekStart = self::WEEK_START): void
    {
        $this->callApprovalRoute('not_approved', $approval, $actor, $weekStart);
    }

    private function callApprovalRoute(string $route, Approval $approval, User $actor, string $weekStart, string $extra = ''): void
    {
        $this->loginAs($actor);
        $this->request(
            $this->client,
            '/approval/' . $route . '/' . $approval->getId() .
            '?user=' . $this->submitter->getId() . '&date=' . $weekStart . $extra
        );
    }
}
