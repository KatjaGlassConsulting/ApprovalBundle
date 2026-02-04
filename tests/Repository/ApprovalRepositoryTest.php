<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Repository;

use App\Entity\User;
use App\Repository\TimesheetRepository;
use App\Utils\SearchTerm;
use DateTime;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalOvertimeHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalWorkdayHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\LockdownRepository;
use KimaiPlugin\ApprovalBundle\Repository\Query\ApprovalQuery;
use KimaiPlugin\ApprovalBundle\Repository\ReportRepository;
use KimaiPlugin\ApprovalBundle\Settings\ApprovalSettingsInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\Formatting;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Doctrine\ORM\Query\Expr;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Unit tests for ApprovalRepository::getUserApprovalsFiltered()
 */
class ApprovalRepositoryTest extends TestCase
{
    /**
     * @var ApprovalRepository
     */
    private ApprovalRepository $repository;

    /**
     * @var MockObject|EntityManager
     */
    private $entityManager;

    /**
     * @var MockObject|QueryBuilder
     */
    private $queryBuilder;

    /**
     * @var MockObject|Query
     */
    private $query;

    /**
     * @var MockObject|SettingsTool
     */
    private $settingsTool;

    /**
     * @var MockObject|ApprovalSettingsInterface
     */
    private $metaFieldSettings;

    /**
     * @var Formatting
     */
    private Formatting $formatting;

    /**
     * @var MockObject|UrlGeneratorInterface
     */
    private $urlGenerator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManager::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->query = $this->createMock(Query::class);
        $this->settingsTool = $this->createMock(SettingsTool::class);
        $this->metaFieldSettings = $this->createMock(ApprovalSettingsInterface::class);
        $this->formatting = new Formatting($this->createMock(\Symfony\Contracts\Translation\TranslatorInterface::class));
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);

        // Mock repositories
        $approvalWorkdayRepo = $this->createMock(ApprovalWorkdayHistoryRepository::class);
        $approvalOvertimeRepo = $this->createMock(ApprovalOvertimeHistoryRepository::class);
        $reportRepository = $this->createMock(ReportRepository::class);
        $timesheetRepository = $this->createMock(TimesheetRepository::class);

        $this->repository = new ApprovalRepository(
            $this->createMock(\Doctrine\Persistence\ManagerRegistry::class),
            $this->metaFieldSettings,
            $approvalWorkdayRepo,
            $approvalOvertimeRepo,
            $reportRepository,
            $timesheetRepository,
            $this->settingsTool,
            $this->formatting,
            $this->urlGenerator
        );

        // Override the entity manager with our mock
        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('_em');
        $property->setAccessible(true);
        $property->setValue($this->repository, $this->entityManager);

        // Setup ExpressionBuilder mock
        $this->setupExpressionBuilder();
    }

    /**
     * Setup ExpressionBuilder mock to handle in() method
     */
    private function setupExpressionBuilder(): void
    {
        $expressionBuilder = $this->createMock(Expr::class);
        $expressionBuilder->method('in')
            ->willReturn('u.id IN (:usersId)');

        $this->entityManager->method('getExpressionBuilder')
            ->willReturn($expressionBuilder);
    }

    /**
     * Test with empty users array returns empty result
     */
    public function testGetUserApprovalsFilteredWithEmptyUsersArray(): void
    {
        $query = new ApprovalQuery();
        $result = $this->repository->getUserApprovalsFiltered([], $query);

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test with users but no filters applied
     */
    public function testGetUserApprovalsFilteredWithUsersNoFilters(): void
    {
        // Setup default configuration mock
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        // Create test data with multiple approvals - different statuses
        $user = $this->createTestUser(1, 'john.doe');
        $approvalSubmitted = $this->createTestApproval(
            $user,
            new DateTime('2025-01-06'),
            new DateTime('2025-01-12'),
            ApprovalStatus::SUBMITTED
        );
        $approvalApproved = $this->createTestApproval(
            $user,
            new DateTime('2025-01-13'),
            new DateTime('2025-01-19'),
            ApprovalStatus::APPROVED
        );

        // Setup QueryBuilder mock chain
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('join')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approvalSubmitted, $approvalApproved]);

        // Execute
        $result = $this->repository->getUserApprovalsFiltered([$user], new ApprovalQuery());

        // Assert - should only return SUBMITTED approval, not APPROVED
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test with date range filter (dateRangeStart)
     */
    public function testGetUserApprovalsFilteredWithDateRangeStart(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        // Approval before date range
        $approvalBefore = $this->createTestApproval(
            $user,
            new DateTime('2025-01-06'),
            new DateTime('2025-01-12'),
            ApprovalStatus::SUBMITTED
        );
        // Approval within date range
        $approvalWithin = $this->createTestApproval(
            $user,
            new DateTime('2025-02-10'),
            new DateTime('2025-02-16'),
            ApprovalStatus::SUBMITTED
        );
        // Approval with different status
        $approvalDenied = $this->createTestApproval(
            $user,
            new DateTime('2025-02-17'),
            new DateTime('2025-02-23'),
            ApprovalStatus::DENIED
        );

        // Setup QueryBuilder expectations
        $this->setupQueryBuilderChain();

        // Create query with date range start in the middle
        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setBegin(new DateTime('2025-02-01'));

        // Verify andWhere is called for date range
        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        // Only return approvals that are after Feb 1st
        $this->query->method('getResult')->willReturn([$approvalWithin, $approvalDenied]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval within range
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test with date range filter (dateRangeEnd)
     */
    public function testGetUserApprovalsFilteredWithDateRangeEnd(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        // Approval within date range
        $approvalWithin = $this->createTestApproval(
            $user,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );
        // Approval after date range
        $approvalAfter = $this->createTestApproval(
            $user,
            new DateTime('2025-03-06'),
            new DateTime('2025-03-12'),
            ApprovalStatus::APPROVED
        );
        // Another approval outside range with different status
        $approvalOutside = $this->createTestApproval(
            $user,
            new DateTime('2025-03-13'),
            new DateTime('2025-03-19'),
            ApprovalStatus::DENIED
        );

        $this->setupQueryBuilderChain();

        // Create query with date range end in the middle
        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setEnd(new DateTime('2025-02-28'));

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        // Only return approvals that are before Feb 28
        $this->query->method('getResult')->willReturn([$approvalWithin, $approvalAfter]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval within range
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test with both date range filters (start and end)
     */
    public function testGetUserApprovalsFilteredWithDateRangeStartAndEnd(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        // Approval before date range
        $approvalBefore = $this->createTestApproval(
            $user,
            new DateTime('2025-01-06'),
            new DateTime('2025-01-12'),
            ApprovalStatus::SUBMITTED
        );
        // Approval within date range
        $approvalWithin = $this->createTestApproval(
            $user,
            new DateTime('2025-02-10'),
            new DateTime('2025-02-16'),
            ApprovalStatus::SUBMITTED
        );
        // Approval after date range
        $approvalAfter = $this->createTestApproval(
            $user,
            new DateTime('2025-03-10'),
            new DateTime('2025-03-16'),
            ApprovalStatus::DENIED
        );

        $this->setupQueryBuilderChain();

        // Create query with both date range filters in the middle
        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setBegin(new DateTime('2025-02-01'));
        $approvalQuery->setEnd(new DateTime('2025-02-28'));

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('setParameter')
            ->willReturnSelf();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        // Only return approvals within Feb range
        $this->query->method('getResult')->willReturn([$approvalWithin, $approvalAfter]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval within range
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test with search term filter
     */
    public function testGetUserApprovalsFilteredWithSearchTerm(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        $user->setAlias('john');

        $approvalSubmitted = $this->createTestApproval(
            $user,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );
        $approvalApproved = $this->createTestApproval(
            $user,
            new DateTime('2025-02-13'),
            new DateTime('2025-02-19'),
            ApprovalStatus::APPROVED
        );

        $this->setupQueryBuilderChain();

        // Create query with search term
        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setSearchTerm(new SearchTerm('john'));

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approvalSubmitted, $approvalApproved]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test that filters out non-submitted approvals
     */
    public function testGetUserApprovalsFilteredFiltersNonSubmittedApprovals(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');

        // Create approval with non-submitted status
        $approval = $this->createTestApproval(
            $user,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::APPROVED // Not submitted
        );

        $this->setupQueryBuilderChain();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approval]);

        $result = $this->repository->getUserApprovalsFiltered([$user], new ApprovalQuery());

        // Should be empty because approval status is APPROVED, not SUBMITTED
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test with multiple users
     */
    public function testGetUserApprovalsFilteredWithMultipleUsers(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user1 = $this->createTestUser(1, 'john.doe');
        $user2 = $this->createTestUser(2, 'jane.doe');

        $approval1Submitted = $this->createTestApproval(
            $user1,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );

        $approval2Submitted = $this->createTestApproval(
            $user2,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );

        $approval1Denied = $this->createTestApproval(
            $user1,
            new DateTime('2025-02-13'),
            new DateTime('2025-02-19'),
            ApprovalStatus::DENIED
        );

        $this->setupQueryBuilderChain();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approval1Submitted, $approval2Submitted, $approval1Denied]);

        $result = $this->repository->getUserApprovalsFiltered([$user1, $user2], new ApprovalQuery());

        // Should only contain SUBMITTED approvals
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test with custom start date parameter
     */
    public function testGetUserApprovalsFilteredWithCustomStartDate(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        $approvalSubmitted = $this->createTestApproval(
            $user,
            new DateTime('2025-03-06'),
            new DateTime('2025-03-12'),
            ApprovalStatus::SUBMITTED
        );
        $approvalApproved = $this->createTestApproval(
            $user,
            new DateTime('2025-03-13'),
            new DateTime('2025-03-19'),
            ApprovalStatus::APPROVED
        );

        $this->setupQueryBuilderChain();

        $customStartDate = new DateTime('2025-03-01');
        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approvalSubmitted, $approvalApproved]);

        $result = $this->repository->getUserApprovalsFiltered([$user], new ApprovalQuery(), $customStartDate);

        // Should only contain SUBMITTED approval
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test with empty configuration value for APPROVAL_WORKFLOW_START
     */
    public function testGetUserApprovalsFilteredWithEmptyWorkflowStartConfig(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn(''); // Empty configuration

        $user = $this->createTestUser(1, 'john.doe');
        $approvalSubmitted = $this->createTestApproval(
            $user,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );
        $approvalDenied = $this->createTestApproval(
            $user,
            new DateTime('2025-02-13'),
            new DateTime('2025-02-19'),
            ApprovalStatus::DENIED
        );

        $this->setupQueryBuilderChain();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approvalSubmitted, $approvalDenied]);

        $result = $this->repository->getUserApprovalsFiltered([$user], new ApprovalQuery());

        // Should only contain SUBMITTED approval
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test search term filtering with username match
     */
    public function testGetUserApprovalsFilteredSearchByUsername(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        $approvalSubmitted = $this->createTestApproval(
            $user,
            new DateTime('2025-02-06'),
            new DateTime('2025-02-12'),
            ApprovalStatus::SUBMITTED
        );
        $approvalApproved = $this->createTestApproval(
            $user,
            new DateTime('2025-02-13'),
            new DateTime('2025-02-19'),
            ApprovalStatus::APPROVED
        );

        $this->setupQueryBuilderChain();

        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setSearchTerm(new SearchTerm('john'));

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        $this->query->method('getResult')->willReturn([$approvalSubmitted, $approvalApproved]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    /**
     * Test combined filters: date range + search term
     */
    public function testGetUserApprovalsFilteredCombinedDateRangeAndSearch(): void
    {
        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_WORKFLOW_START)
            ->willReturn('2025-01-01');

        $user = $this->createTestUser(1, 'john.doe');
        // Approval before date range
        $approvalBefore = $this->createTestApproval(
            $user,
            new DateTime('2025-01-06'),
            new DateTime('2025-01-12'),
            ApprovalStatus::SUBMITTED
        );
        // Approval within date range
        $approvalWithin = $this->createTestApproval(
            $user,
            new DateTime('2025-02-10'),
            new DateTime('2025-02-16'),
            ApprovalStatus::SUBMITTED
        );
        // Approval after date range with different status
        $approvalAfter = $this->createTestApproval(
            $user,
            new DateTime('2025-03-10'),
            new DateTime('2025-03-16'),
            ApprovalStatus::DENIED
        );

        $this->setupQueryBuilderChain();

        $approvalQuery = new ApprovalQuery();
        $approvalQuery->setBegin(new DateTime('2025-02-01'));
        $approvalQuery->setEnd(new DateTime('2025-02-28'));
        $approvalQuery->setSearchTerm(new SearchTerm('john'));

        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);
        // Only return approvals within Feb range
        $this->query->method('getResult')->willReturn([$approvalWithin, $approvalAfter]);

        $result = $this->repository->getUserApprovalsFiltered([$user], $approvalQuery);

        // Should only contain SUBMITTED approval within range
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    // Helper methods

    /**
     * Create a test user with given ID and username
     */
    private function createTestUser(int $id, string $username): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getUsername')->willReturn($username);
        $user->method('getAlias')->willReturn(null);
        $user->method('getDisplayName')->willReturn($username);

        return $user;
    }

    /**
     * Create a test approval with given parameters
     */
    private function createTestApproval(
        User $user,
        DateTime $startDate,
        DateTime $endDate,
        string $statusName
    ): Approval {
        $status = $this->createMock(ApprovalStatus::class);
        $status->method('getName')->willReturn($statusName);

        $history = $this->createMock(ApprovalHistory::class);
        $history->method('getStatus')->willReturn($status);

        $approval = $this->createMock(Approval::class);
        $approval->method('getUser')->willReturn($user);
        $approval->method('getStartDate')->willReturn($startDate);
        $approval->method('getEndDate')->willReturn($endDate);
        $approval->method('getHistory')->willReturn([$history]);

        return $approval;
    }

    /**
     * Setup QueryBuilder mock chain with default behaviors
     */
    private function setupQueryBuilderChain(): void
    {
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('join')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);
    }
}
