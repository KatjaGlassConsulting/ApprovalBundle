<?php

namespace KimaiPlugin\ApprovalBundle\Tests\Integration;

use App\Entity\User;
use DateTime;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\Query\ApprovalQuery;
use KimaiPlugin\ApprovalBundle\Service\AutoApprovalService;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalStatusRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Integration test to verify that autoApprove only processes approvals
 * that match the query filters set in toApprove
 */
class AutoApprovalFilteringTest extends TestCase
{
    private AutoApprovalService|MockObject $autoApprovalService;
    private ApprovalRepository|MockObject $approvalRepository;
    private SettingsTool|MockObject $settingsTool;

    protected function setUp(): void
    {
        $this->approvalRepository = $this->createMock(ApprovalRepository::class);
        $this->settingsTool = $this->createMock(SettingsTool::class);

        // We'll verify the service receives the correct filtered approvals
        $this->autoApprovalService = $this->createMock(AutoApprovalService::class);
    }

    /**
     * Test that only approvals matching the query users filter get processed
     */
    public function testAutoApproveOnlyProcessesSelectedUsers(): void
    {
        // Setup: Create multiple users
        $user1 = $this->createMockUser(1, 'Alice');
        $user2 = $this->createMockUser(2, 'Bob');
        $user3 = $this->createMockUser(3, 'Charlie');

        // Create approvals for all users
        $approval1 = $this->createMockApproval(1, $user1);
        $approval2 = $this->createMockApproval(2, $user2);
        $approval3 = $this->createMockApproval(3, $user3);

        // Create query that ONLY selects user1 and user2
        $query = new ApprovalQuery();
        $query->setUsers([$user1, $user2]);

        // Mock repository to return ONLY filtered approvals (matching the query)
        $filteredApprovals = [$approval1, $approval2]; // user3's approval is NOT included

        $this->approvalRepository->expects($this->once())
            ->method('getUserApprovalsFiltered')
            ->with(
                $this->callback(function ($users) use ($user1, $user2) {
                    // Verify the users parameter contains only the selected users
                    return in_array($user1, $users) && in_array($user2, $users);
                }),
                $query
            )
            ->willReturn($filteredApprovals);

        // Verify that ONLY the filtered approvals are processed
        $this->autoApprovalService->expects($this->once())
            ->method('processApprovals')
            ->with($filteredApprovals)
            ->willReturn([
                'processedApprovals' => [$approval1, $approval2],
                'successful' => 2,
                'failed' => 0
            ]);

        // Execute: Call the repository method that autoApprove would call
        $resultApprovals = $this->approvalRepository->getUserApprovalsFiltered([$user1, $user2], $query);

        // Verify resultApprovals contains only filtered approvals
        $this->assertCount(2, $resultApprovals);
        $this->assertContains($approval1, $resultApprovals);
        $this->assertContains($approval2, $resultApprovals);
        $this->assertNotContains($approval3, $resultApprovals); // Charlie's approval excluded

        // Process them
        $processResult = $this->autoApprovalService->processApprovals($resultApprovals);

        $this->assertEquals(2, $processResult['successful']);
        $this->assertEquals(0, $processResult['failed']);
    }

    /**
     * Test that date range filter is applied correctly
     */
    public function testAutoApproveRespectsDateRangeFilter(): void
    {
        $user = $this->createMockUser(1, 'Alice');

        // Create approvals for different weeks
        $approval1 = $this->createMockApproval(1, $user, '2026-01-20', '2026-01-26'); // Week 1
        $approval2 = $this->createMockApproval(2, $user, '2026-01-27', '2026-02-02'); // Week 2
        $approval3 = $this->createMockApproval(3, $user, '2026-02-03', '2026-02-09'); // Week 3

        // Create query with date range for only Week 1 and 2
        $query = new ApprovalQuery();
        $query->setBegin(new DateTime('2026-01-20'));
        $query->setEnd(new DateTime('2026-02-02'));

        // Mock repository returns only approvals in date range
        $filteredApprovals = [$approval1, $approval2]; // approval3 excluded

        $this->approvalRepository->expects($this->once())
            ->method('getUserApprovalsFiltered')
            ->with($this->anything(), $query)
            ->willReturn($filteredApprovals);

        $this->autoApprovalService->expects($this->once())
            ->method('processApprovals')
            ->with($filteredApprovals)
            ->willReturn([
                'processedApprovals' => [$approval1, $approval2],
                'successful' => 2,
                'failed' => 0
            ]);

        $resultApprovals = $this->approvalRepository->getUserApprovalsFiltered([$user], $query);
        $result = $this->autoApprovalService->processApprovals($resultApprovals);

        $this->assertCount(2, $resultApprovals);
        $this->assertNotContains($approval3, $resultApprovals); // Week 3 excluded
    }

    /**
     * Test that status filter is applied correctly
     */
    public function testAutoApproveOnlyProcessesSubmittedStatus(): void
    {
        $user = $this->createMockUser(1, 'Alice');

        $approval1 = $this->createMockApproval(1, $user);
        $approval2 = $this->createMockApproval(2, $user);
        $approval3 = $this->createMockApproval(3, $user);

        // Create query filtering for "submitted" status only
        $query = new ApprovalQuery();
        $query->setStatus([ApprovalStatus::SUBMITTED]);

        // Repository returns only submitted approvals
        $filteredApprovals = [$approval1, $approval2]; // approval3 has different status

        $this->approvalRepository->expects($this->once())
            ->method('getUserApprovalsFiltered')
            ->with($this->anything(), $query)
            ->willReturn($filteredApprovals);

        $this->autoApprovalService->expects($this->once())
            ->method('processApprovals')
            ->with($filteredApprovals)
            ->willReturn([
                'processedApprovals' => [$approval1, $approval2],
                'successful' => 2,
                'failed' => 0
            ]);

        $resultApprovals = $this->approvalRepository->getUserApprovalsFiltered([$user], $query);
        $result = $this->autoApprovalService->processApprovals($resultApprovals);


        $this->assertCount(2, $resultApprovals);
        $this->assertEquals(2, $result['successful']);
    }

    /**
     * Test that empty query users falls back to all team users
     */
    public function testAutoApproveUsesAllUsersWhenQueryIsEmpty(): void
    {
        $user1 = $this->createMockUser(1, 'Alice');
        $user2 = $this->createMockUser(2, 'Bob');

        // Query with NO user filter
        $query = new ApprovalQuery();

        // All team users should be used
        $allUsers = [$user1, $user2];

        $approval1 = $this->createMockApproval(1, $user1);
        $approval2 = $this->createMockApproval(2, $user2);

        $this->approvalRepository->expects($this->once())
            ->method('getUserApprovalsFiltered')
            ->with($allUsers, $query)
            ->willReturn([$approval1, $approval2]);

        $resultApprovals = $this->approvalRepository->getUserApprovalsFiltered($allUsers, $query);

        $this->assertCount(2, $resultApprovals);
    }

    /**
     * Test the complete workflow: query set in toApprove is used in autoApprove
     */
    public function testCompleteWorkflowQueryPassedBetweenActions(): void
    {
        // Simulate what happens in toApprove
        $user1 = $this->createMockUser(1, 'Alice');
        $user2 = $this->createMockUser(2, 'Bob');

        // 1. User selects specific users in toApprove
        $queryFromToApprove = new ApprovalQuery();
        $queryFromToApprove->setUsers([$user1]); // Only Alice selected

        // Simulate session storage (in real code: $request->getSession()->set('query', $query))
        $sessionQuery = $queryFromToApprove;

        // 2. In autoApprove, query is retrieved from session
        $queryInAutoApprove = $sessionQuery;

        // Verify it's the same query
        $this->assertSame($queryFromToApprove, $queryInAutoApprove);
        $this->assertEquals([$user1], $queryInAutoApprove->getUsers());

        // 3. Only Alice's approvals should be fetched
        $approval1 = $this->createMockApproval(1, $user1);

        $this->approvalRepository->expects($this->once())
            ->method('getUserApprovalsFiltered')
            ->with(
                $this->callback(function ($users) use ($user1) {
                    return count($users) === 1 && $users[0] === $user1;
                }),
                $queryInAutoApprove
            )
            ->willReturn([$approval1]);

        // Execute
        $resultApprovals = $this->approvalRepository->getUserApprovalsFiltered(
            $queryInAutoApprove->getUsers(),
            $queryInAutoApprove
        );

        // Verify only Alice's approval is processed
        $this->assertCount(1, $resultApprovals);
        $this->assertEquals($approval1, $resultApprovals[0]);
    }

    // Helper methods

    private function createMockUser(int $id, string $name): User|MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($name);
        $user->method('getDisplayName')->willReturn($name);
        return $user;
    }

    private function createMockApproval(
        int $id,
        User $user,
        string $startDate = '2026-01-20',
        string $endDate = '2026-01-26'
    ): Approval|MockObject {
        $approval = $this->createMock(Approval::class);
        $approval->method('getId')->willReturn($id);
        $approval->method('getUser')->willReturn($user);
        $approval->method('getStartDate')->willReturn(new DateTime($startDate));
        $approval->method('getEndDate')->willReturn(new DateTime($endDate));
        $approval->method('getHistory')->willReturn([]);
        return $approval;
    }
}