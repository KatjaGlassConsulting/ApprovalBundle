<?php

namespace KimaiPlugin\ApprovalBundle\Tests\Service;

use App\Entity\User;
use App\Form\Model\DateRange;
use App\Repository\Query\BaseQuery;
use App\Repository\Query\TimesheetQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use DateTime;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\Query\ApprovalQuery;
use KimaiPlugin\ApprovalBundle\Service\ApprovalDataService;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ApprovalDataServiceTest extends TestCase
{
    private ApprovalDataService $service;
    private MockObject|ApprovalRepository $approvalRepository;
    private MockObject|UserRepository $userRepository;
    private MockObject|TimesheetRepository $timesheetRepository;
    private MockObject|BreakTimeCheckToolGER $breakTimeCheckToolGER;
    private MockObject|SettingsTool $settingsTool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->approvalRepository = $this->createMock(ApprovalRepository::class);
        $this->userRepository = $this->createMock(UserRepository::class);
        $this->timesheetRepository = $this->createMock(TimesheetRepository::class);
        $this->breakTimeCheckToolGER = $this->createMock(BreakTimeCheckToolGER::class);
        $this->settingsTool = $this->createMock(SettingsTool::class);

        $this->service = new ApprovalDataService(
            $this->approvalRepository,
            $this->userRepository,
            $this->timesheetRepository,
            $this->breakTimeCheckToolGER,
            $this->settingsTool
        );
    }

    /**
     * Test fetchAndFilterApprovalRows with no filters
     */
    public function testFetchAndFilterApprovalRowsWithoutFilters(): void
    {
        $users = [$this->createMock(User::class)];
        $query = new ApprovalQuery();

        $mockRows = [
            ['userId' => 1, 'user' => 'John Doe', 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'user' => 'Jane Smith', 'status' => ApprovalStatus::APPROVED],
        ];


        $this->approvalRepository->expects($this->once())
            ->method('findAllWeek')
            ->with($users)
            ->willReturn($mockRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->with(ConfigEnum::APPROVAL_HIDE_APPROVED_NY, false)
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        $this->assertCount(2, $result);
        $this->assertEquals($mockRows, array_values($result));
    }

    /**
     * Test fetchAndFilterApprovalRows with hide approved enabled
     */
    public function testFetchAndFilterApprovalRowsHidesApproved(): void
    {
        $users = [$this->createMock(User::class)];
        $query = new ApprovalQuery();

        $allRows = [
            ['userId' => 1, 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'status' => ApprovalStatus::APPROVED],
        ];

        $filteredRows = [
            ['userId' => 1, 'status' => ApprovalStatus::SUBMITTED],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->with(ConfigEnum::APPROVAL_HIDE_APPROVED_NY, false)
            ->willReturn(true);

        $this->approvalRepository->expects($this->once())
            ->method('filterWeeksNotApproved')
            ->with($allRows)
            ->willReturn($filteredRows);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        $this->assertCount(1, $result);
    }

    /**
     * Test fetchAndFilterApprovalRows filters by users
     */
    public function testFetchAndFilterApprovalRowsFiltersByUsers(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);

        $user2 = $this->createMock(User::class);
        $user2->method('getId')->willReturn(2);

        $users = [$user1, $user2];
        $query = new ApprovalQuery();
        $query->setUsers([$user1]);

        $allRows = [
            ['userId' => 1, 'user' => 'User1', 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'user' => 'User2', 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 3, 'user' => 'User3', 'status' => ApprovalStatus::SUBMITTED],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        $this->assertCount(1, $result);
        $filteredResult = array_values($result);
        $this->assertEquals(1, $filteredResult[0]['userId']);
    }

    /**
     * Test fetchAndFilterApprovalRows filters by date range
     */
    public function testFetchAndFilterApprovalRowsFiltersByDateRange(): void
    {
        $users = [$this->createMock(User::class)];
        $query = new ApprovalQuery();

        $dateRange = new DateRange();
        $dateRange->setBegin(new DateTime('2026-01-20'));
        $dateRange->setEnd(new DateTime('2026-01-26'));
        $query->setDateRange($dateRange);

        $allRows = [
            [
                'userId' => 1,
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 2,
                'week' => (object) ['value' => new DateTime('2026-01-13')],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 3,
                'week' => (object) ['value' => new DateTime('2026-01-27')],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        // Should include row with week starting 2026-01-20 (within range)
        $this->assertEquals(1, count($result));
        $this->assertEquals(1, array_values($result)[0]['userId']);
    }

    /**
     * Test fetchAndFilterApprovalRows filters by status
     */
    public function testFetchAndFilterApprovalRowsFiltersByStatus(): void
    {
        $users = [$this->createMock(User::class)];
        $query = new ApprovalQuery();
        $query->setStatus([ApprovalStatus::SUBMITTED, ApprovalStatus::NOT_SUBMITTED]);

        $allRows = [
            ['userId' => 1, 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'status' => ApprovalStatus::APPROVED],
            ['userId' => 3, 'status' => ApprovalStatus::NOT_SUBMITTED],
            ['userId' => 4, 'status' => ApprovalStatus::DENIED],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        $this->assertCount(2, $result);
        $resultArray = array_values($result);
        $this->assertContains($resultArray[0]['status'], [ApprovalStatus::SUBMITTED, ApprovalStatus::NOT_SUBMITTED]);
        $this->assertContains($resultArray[1]['status'], [ApprovalStatus::SUBMITTED, ApprovalStatus::NOT_SUBMITTED]);
    }

    /**
     * Test fetchAndFilterApprovalRows filters by search term
     */
    public function testFetchAndFilterApprovalRowsFiltersBySearchTerm(): void
    {
        $users = [$this->createMock(User::class)];
        $query = new ApprovalQuery();

        // Use real SearchTerm instead of mock
        $searchTerm = new \App\Utils\SearchTerm('John');
        $query->setSearchTerm($searchTerm);

        $allRows = [
            [
                'userId' => 1,
                'user' => 'John Doe',
                'week' => (object) ['label' => 'Week 4'],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 2,
                'user' => 'Jane Smith',
                'week' => (object) ['label' => 'Week 4'],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        $this->assertCount(1, $result);
        $filteredResult = array_values($result);
        $this->assertStringContainsString('John', $filteredResult[0]['user']);
    }

    /**
     * Test enrichRowsWithErrors with break time errors
     */
    public function testEnrichRowsWithErrorsAddsErrorFlag(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $rows = [
            [
                'userId' => 1,
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 1,
                'week' => (object) ['value' => new DateTime('2026-01-27')],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->userRepository->method('find')
            ->with(1)
            ->willReturn($user);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        // First row has errors, second doesn't
        $this->breakTimeCheckToolGER->method('checkBreakTime')
            ->willReturnOnConsecutiveCalls(
                ['2026-01-20' => 'Break time violation'],
                []
            );

        $this->settingsTool->method('isInConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(true);

        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(true);

        $result = $this->service->enrichRowsWithErrors($rows);

        $this->assertTrue($result[0]['hasErrors']);
        $this->assertFalse($result[1]['hasErrors']);
    }

    /**
     * Test enrichRowsWithErrors when break checks disabled
     */
    public function testEnrichRowsWithErrorsWhenBreakChecksDisabled(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $rows = [
            [
                'userId' => 1,
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->userRepository->method('find')
            ->with(1)
            ->willReturn($user);

        $this->timesheetRepository->method('getTimesheetsForQuery')
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(false);

        $this->settingsTool->method('getConfiguration')
            ->with(ConfigEnum::APPROVAL_BREAKCHECKS_NY)
            ->willReturn(false);

        // Break time check should not be called
        $this->breakTimeCheckToolGER->expects($this->never())
            ->method('checkBreakTime');

        $result = $this->service->enrichRowsWithErrors($rows);

        $this->assertFalse($result[0]['hasErrors']);
    }

    /**
     * Test categorizeRowsByWeek separates past, current, and future
     */
    public function testCategorizeRowsByWeekSeparatesCorrectly(): void
    {
        // Get the Monday of current week
        $now = new DateTime('now');
        $currentMonday = (clone $now)->modify('next monday')->modify('-1 week');
        $nextMonday = (clone $now)->modify('next monday');

        $pastDate = (clone $currentMonday)->modify('-7 days')->format('Y-m-d');
        $currentDate = $currentMonday->format('Y-m-d');
        $futureDate = $nextMonday->format('Y-m-d');

        $rows = [
            ['userId' => 1, 'startDate' => $pastDate, 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'startDate' => $currentDate, 'status' => ApprovalStatus::NOT_SUBMITTED],
            ['userId' => 3, 'startDate' => $futureDate, 'status' => ApprovalStatus::NOT_SUBMITTED],
        ];

        [$pastRows, $currentRows, $futureRows] = $this->service->categorizeRowsByWeek($rows);

        $this->assertCount(1, $pastRows);
        $this->assertCount(1, $currentRows);
        $this->assertCount(1, $futureRows);

        $this->assertEquals($pastDate, $pastRows[0]['startDate']);
        $this->assertEquals($currentDate, $currentRows[0]['startDate']);
        $this->assertEquals($futureDate, $futureRows[0]['startDate']);
    }

    /**
     * Test categorizeRowsByWeek with all past weeks
     */
    public function testCategorizeRowsByWeekAllPast(): void
    {
        $rows = [
            ['userId' => 1, 'startDate' => '2026-01-06', 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'startDate' => '2025-12-30', 'status' => ApprovalStatus::APPROVED],
        ];

        [$pastRows, $currentRows, $futureRows] = $this->service->categorizeRowsByWeek($rows);

        $this->assertEquals(2, count($pastRows));
        $this->assertEquals(0, count($currentRows));
        $this->assertEquals(0, count($futureRows));
    }

    /**
     * Test categorizeRowsByWeek with empty rows
     */
    public function testCategorizeRowsByWeekWithEmptyRows(): void
    {
        $rows = [];

        [$pastRows, $currentRows, $futureRows] = $this->service->categorizeRowsByWeek($rows);

        $this->assertCount(0, $pastRows);
        $this->assertCount(0, $currentRows);
        $this->assertCount(0, $futureRows);
    }

    /**
     * Test countSubmittedWeeks counts only submitted status
     */
    public function testCountSubmittedWeeksCountsCorrectly(): void
    {
        $rows = [
            ['status' => ApprovalStatus::SUBMITTED],
            ['status' => ApprovalStatus::APPROVED],
            ['status' => ApprovalStatus::SUBMITTED],
            ['status' => ApprovalStatus::NOT_SUBMITTED],
            ['status' => ApprovalStatus::SUBMITTED],
            ['status' => ApprovalStatus::DENIED],
        ];

        $count = $this->service->countSubmittedWeeks($rows);

        $this->assertEquals(3, $count);
    }

    /**
     * Test countSubmittedWeeks with no submitted weeks
     */
    public function testCountSubmittedWeeksWithNoSubmitted(): void
    {
        $rows = [
            ['status' => ApprovalStatus::APPROVED],
            ['status' => ApprovalStatus::NOT_SUBMITTED],
            ['status' => ApprovalStatus::DENIED],
        ];

        $count = $this->service->countSubmittedWeeks($rows);

        $this->assertEquals(0, $count);
    }

    /**
     * Test countSubmittedWeeks with empty rows
     */
    public function testCountSubmittedWeeksWithEmptyRows(): void
    {
        $rows = [];

        $count = $this->service->countSubmittedWeeks($rows);

        $this->assertEquals(0, $count);
    }

    /**
     * Test countSubmittedWeeks with malformed data
     */
    public function testCountSubmittedWeeksWithMalformedData(): void
    {
        $rows = [
            ['status' => ApprovalStatus::SUBMITTED],
            ['noStatus' => 'value'], // Missing status key
            ['status' => ApprovalStatus::SUBMITTED],
        ];

        $count = $this->service->countSubmittedWeeks($rows);

        $this->assertEquals(2, $count);
    }

    /**
     * Test filterByUsers with empty selected users returns all rows
     */
    public function testFilterByUsersWithEmptySelection(): void
    {
        $rows = [
            ['userId' => 1, 'user' => 'User1'],
            ['userId' => 2, 'user' => 'User2'],
        ];

        $query = new ApprovalQuery();
        $query->setUsers([]);

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($rows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, []);

        $this->assertCount(2, $result);
    }

    /**
     * Test filterByDateRange with null date range returns all rows
     */
    public function testFilterByDateRangeWithNullRange(): void
    {
        $rows = [
            ['userId' => 1, 'week' => (object) ['value' => new DateTime('2026-01-20')]],
            ['userId' => 2, 'week' => (object) ['value' => new DateTime('2026-01-27')]],
        ];

        $query = new ApprovalQuery();
        $query->setDateRange(new DateRange()); // No begin or end set

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($rows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, []);

        $this->assertCount(2, $result);
    }

    /**
     * Test filterByStatus with empty status returns all rows
     */
    public function testFilterByStatusWithEmptySelection(): void
    {
        $rows = [
            ['userId' => 1, 'status' => ApprovalStatus::SUBMITTED],
            ['userId' => 2, 'status' => ApprovalStatus::APPROVED],
        ];

        $query = new ApprovalQuery();
        $query->setStatus([]);

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($rows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, []);

        $this->assertCount(2, $result);
    }

    /**
     * Test multiple filters applied together
     */
    public function testMultipleFiltersAppliedTogether(): void
    {
        $user1 = $this->createMock(User::class);
        $user1->method('getId')->willReturn(1);

        $users = [$user1];
        $query = new ApprovalQuery();
        $query->setUsers([$user1]);
        $query->setStatus([ApprovalStatus::SUBMITTED]);

        $dateRange = new DateRange();
        $dateRange->setBegin(new DateTime('2026-01-20'));
        $dateRange->setEnd(new DateTime('2026-01-26'));
        $query->setDateRange($dateRange);

        $allRows = [
            [
                'userId' => 1,
                'user' => 'User1',
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 1,
                'user' => 'User1',
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::APPROVED
            ],
            [
                'userId' => 2,
                'user' => 'User2',
                'week' => (object) ['value' => new DateTime('2026-01-20')],
                'status' => ApprovalStatus::SUBMITTED
            ],
            [
                'userId' => 1,
                'user' => 'User1',
                'week' => (object) ['value' => new DateTime('2026-02-03')],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($allRows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, $users);

        // Should only return rows matching: userId=1, status=submitted, and within date range
        $this->assertCount(1, $result);
        $filteredResult = array_values($result);
        $this->assertEquals(1, $filteredResult[0]['userId']);
        $this->assertEquals(ApprovalStatus::SUBMITTED, $filteredResult[0]['status']);
        $this->assertEquals(
            new DateTime('2026-01-20'),
            $filteredResult[0]['week']->value
        );
    }

    /**
     * Test rowMatchesSearchTerm matches user name
     */
    public function testRowMatchesSearchTermMatchesUser(): void
    {
        $searchTerm = new \App\Utils\SearchTerm('john');

        $query = new ApprovalQuery();
        $query->setSearchTerm($searchTerm);

        $rows = [
            [
                'userId' => 1,
                'user' => 'John Doe',
                'week' => (object) ['label' => 'Week 4'],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($rows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, []);

        $this->assertCount(1, $result);
    }

    /**
     * Test rowMatchesSearchTerm matches status
     */
    public function testRowMatchesSearchTermMatchesStatus(): void
    {
        $searchTerm = new \App\Utils\SearchTerm('submit');

        $query = new ApprovalQuery();
        $query->setSearchTerm($searchTerm);

        $rows = [
            [
                'userId' => 1,
                'user' => 'John Doe',
                'week' => (object) ['label' => 'Week 4'],
                'status' => ApprovalStatus::SUBMITTED
            ],
        ];

        $this->approvalRepository->method('findAllWeek')
            ->willReturn($rows);

        $this->settingsTool->method('getBooleanConfiguration')
            ->willReturn(false);

        $result = $this->service->fetchAndFilterApprovalRows($query, []);

        $this->assertCount(1, $result);
    }

    /**
     * Test getTimesheetsForRow creates correct query
     */
    public function testGetTimesheetsForRowCreatesCorrectQuery(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn(1);

        $weekStart = new DateTime('2026-01-20');
        $row = [
            'userId' => 1,
            'week' => (object) ['value' => $weekStart],
        ];

        $this->userRepository->expects($this->once())
            ->method('find')
            ->with(1)
            ->willReturn($user);

        $this->timesheetRepository->expects($this->once())
            ->method('getTimesheetsForQuery')
            ->with($this->callback(function (TimesheetQuery $query) use ($user) {
                return $query->getUser() === $user &&
                    $query->getOrderBy() === 'date' &&
                    $query->getOrder() === BaseQuery::ORDER_ASC;
            }))
            ->willReturn([]);

        $this->settingsTool->method('isInConfiguration')
            ->willReturn(false);
        $this->settingsTool->method('getConfiguration')
            ->willReturn(false);

        $result = $this->service->enrichRowsWithErrors([$row]);

        $this->assertArrayHasKey('hasErrors', $result[0]);
        $this->assertFalse($result[0]['hasErrors']);
    }
}
