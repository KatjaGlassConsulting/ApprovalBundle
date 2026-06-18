<?php

namespace KimaiPlugin\ApprovalBundle\Tests\Toolbox;

use App\Entity\Timesheet;
use DateTime;
use DateTimeInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\WorkingTimeActToolGER;
use KimaiPlugin\WorkContractBundle\Repository\PublicHolidayRepository;
use KimaiPlugin\WorkContractBundle\Entity\PublicHoliday;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class WorkingTimeActToolGERTest extends TestCase
{
    private WorkingTimeActToolGER $tool;
    private MockObject|PublicHolidayRepository $publicHolidayRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->publicHolidayRepository = $this->createMock(PublicHolidayRepository::class);
        $this->tool = new WorkingTimeActToolGER($this->publicHolidayRepository);
    }

    /**
     * Test compliance when working exactly 8 hours per day (should be compliant)
     */
    public function testCheckComplianceWithExactly8HoursPerDay(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-16'); // Friday (2 weeks)

        // Create 8 timesheets: Monday-Friday for 2 weeks, exactly 8 hours each
        $timesheets = [
            $this->createTimesheet('2026-01-05', 8 * 3600), // Mon Week 1
            $this->createTimesheet('2026-01-06', 8 * 3600), // Tue
            $this->createTimesheet('2026-01-07', 8 * 3600), // Wed
            $this->createTimesheet('2026-01-08', 8 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 8 * 3600), // Fri
            $this->createTimesheet('2026-01-12', 8 * 3600), // Mon Week 2
            $this->createTimesheet('2026-01-13', 8 * 3600), // Tue
            $this->createTimesheet('2026-01-14', 8 * 3600), // Wed
        ];

        // Mock no public holidays
        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1, // publicHolidayGroupId
            $periodStart,
            $periodEnd
        );

        // 2 weeks = 11 workdays (Mon-Fri, excluding weekends)
        // Total hours: 8 timesheets * 8 hours = 64 hours
        // Average: 64 / 11 hours/day
        $this->assertTrue($result['compliance']);
        $this->assertEquals(64.0, $result['total_hours']);
        $this->assertEquals(11, $result['workdays']);
        $this->assertEquals(64.0 / 11, $result['average']);
    }

    /**
     * Test compliance with one Sunday (should be excluded from workdays)
     */
    public function testCheckComplianceExcludesSunday(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-11'); // Sunday

        // Create 7 timesheets including a Sunday
        $timesheets = [
            $this->createTimesheet('2026-01-05', 8 * 3600), // Mon
            $this->createTimesheet('2026-01-06', 8 * 3600), // Tue
            $this->createTimesheet('2026-01-07', 8 * 3600), // Wed
            $this->createTimesheet('2026-01-08', 8 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 8 * 3600), // Fri
            $this->createTimesheet('2026-01-10', 6 * 3600), // Sat
            $this->createTimesheet('2026-01-11', 5 * 3600), // Sun (should be excluded)
        ];

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1,
            $periodStart,
            $periodEnd
        );

        // Workdays: Mon-Sat = 6 days (Sunday excluded)
        // Total: 8+8+8+8+8+6 = 46 hours (Sunday's 5 hours not counted)
        // Average: 46 / 6 = 7.67 hours/day
        $this->assertTrue($result['compliance']);
        $this->assertEquals(7.666666666666667, $result['average']);
        $this->assertEquals(46.0, $result['total_hours']);
        $this->assertEquals(6, $result['workdays']);
    }

    /**
     * Test compliance with public holidays (should be excluded)
     */
    public function testCheckComplianceExcludesPublicHolidays(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-16'); // Friday

        // Create 8 timesheets
        $timesheets = [
            $this->createTimesheet('2026-01-05', 8 * 3600), // Mon
            $this->createTimesheet('2026-01-06', 8 * 3600), // Tue (Public Holiday)
            $this->createTimesheet('2026-01-07', 8 * 3600), // Wed
            $this->createTimesheet('2026-01-08', 8 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 8 * 3600), // Fri
            $this->createTimesheet('2026-01-12', 8 * 3600), // Mon
            $this->createTimesheet('2026-01-13', 8 * 3600), // Tue (Public Holiday)
            $this->createTimesheet('2026-01-14', 8 * 3600), // Wed
        ];

        // Mock two public holidays: 2026-01-06 and 2026-01-13
        $holiday1 = $this->createMock(PublicHoliday::class);
        $holiday1->method('getDate')->willReturn(new \DateTimeImmutable('2026-01-06'));

        $holiday2 = $this->createMock(PublicHoliday::class);
        $holiday2->method('getDate')->willReturn(new \DateTimeImmutable('2026-01-13'));

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([$holiday1, $holiday2]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1,
            $periodStart,
            $periodEnd
        );

        // 11 weekdays - 2 holidays = 9 workdays
        // Total: 48 hours, but holidays excluded from count
        // Average: 48 / 9  hours/day
        $this->assertTrue($result['compliance']);
        $this->assertEquals(48.0, $result['total_hours']);
        $this->assertEquals(9, $result['workdays']);
        $this->assertEquals(48.0 / 9, $result['average']);
    }

    /**
     * Test non-compliance when average exceeds 8 hours per day
     */
    public function testCheckComplianceWithExcessiveHours(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-09'); // Friday

        // Create 7 timesheets with excessive hours
        $timesheets = [
            $this->createTimesheet('2026-01-05', 10 * 3600), // Mon
            $this->createTimesheet('2026-01-06', 10 * 3600), // Tue
            $this->createTimesheet('2026-01-07', 9 * 3600),  // Wed
            $this->createTimesheet('2026-01-08', 10 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 10 * 3600), // Fri
            $this->createTimesheet('2026-01-10', 6 * 3600),  // Sat
            $this->createTimesheet('2026-01-11', 5 * 3600),  // Sun (excluded)
        ];

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1,
            $periodStart,
            $periodEnd
        );

        // Workdays: Mon-Sat = 6 days
        // Total: 10+10+9+10+10 = 49 hours
        // Average: 49 / 5 = 9.8 hours/day (exceeds 8 hours)
        $this->assertFalse($result['compliance']);
        $this->assertEquals(49.0, $result['total_hours']);
        $this->assertEquals(5, $result['workdays']);
        $this->assertEquals(49.0 / 5, $result['average']);
    }

    /**
     * Test empty workdays are counted with 0 hours
     */
    public function testCheckComplianceWithEmptyWorkdays(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-16'); // Friday (2 weeks)

        // Create only 5 timesheets (1 week of work, 1 week empty)
        $timesheets = [
            $this->createTimesheet('2026-01-05', 8 * 3600), // Mon Week 1
            $this->createTimesheet('2026-01-06', 8 * 3600), // Tue
            $this->createTimesheet('2026-01-07', 8 * 3600), // Wed
            $this->createTimesheet('2026-01-08', 8 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 8 * 3600), // Fri
            // Week 2: No timesheets (empty workdays)
        ];

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1,
            $periodStart,
            $periodEnd
        );

        // 10 workdays total (2 weeks Mon-Fri)
        // Total: 40 hours (only first week)
        // Average: 40 / 11 hours/day (empty days count as 0)
        $this->assertTrue($result['compliance']);
        $this->assertEquals(40.0, $result['total_hours']);
        $this->assertEquals(11, $result['workdays']);
        $this->assertEquals(40.0 / 11, $result['average']);
    }

    /**
     * Test compliance with mixed scenario: Sunday, holidays, empty days
     */
    public function testCheckComplianceWithMixedScenario(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-18'); // Sunday (2 weeks)

        // Create 8 timesheets with varied hours
        $timesheets = [
            $this->createTimesheet('2026-01-05', 7 * 3600),  // Mon
            $this->createTimesheet('2026-01-06', 9 * 3600),  // Tue (Public Holiday - excluded)
            $this->createTimesheet('2026-01-07', 8 * 3600),  // Wed
            $this->createTimesheet('2026-01-08', 7.5 * 3600), // Thu
            // 2026-01-09 Fri - Empty workday
            $this->createTimesheet('2026-01-10', 6 * 3600),  // Sat
            $this->createTimesheet('2026-01-11', 5 * 3600),  // Sun (excluded)
            $this->createTimesheet('2026-01-12', 8 * 3600),  // Mon
            $this->createTimesheet('2026-01-13', 8 * 3600),  // Tue (Public Holiday - excluded)
            // Rest of week 2 empty
        ];

        // Mock two public holidays
        $holiday1 = $this->createMock(PublicHoliday::class);
        $holiday1->method('getDate')->willReturn(new \DateTimeImmutable('2026-01-06'));

        $holiday2 = $this->createMock(PublicHoliday::class);
        $holiday2->method('getDate')->willReturn(new \DateTimeImmutable('2026-01-13'));

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([$holiday1, $holiday2]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            1,
            $periodStart,
            $periodEnd
        );

        // 2 weeks = 14 days total
        // - 2 Sundays (Jan 11, 18) = 12 days
        // - 2 holidays (Jan 6, 13) = 10 workdays
        // Total hours: 7+8+7.5+6+8 = 53.5 (holidays counted in total)
        // Average: 36.5 / 10 = 5.35 hours/day
        $this->assertTrue($result['compliance']);
        $this->assertEquals(36.5, $result['total_hours']);
        $this->assertEquals(10, $result['workdays']);
        $this->assertEquals(36.5 / 10, $result['average']);
    }

    /**
     * Test without public holiday repository (null)
     */
    public function testCheckComplianceWithoutPublicHolidayRepository(): void
    {
        $tool = new WorkingTimeActToolGER(null);

        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-09'); // Friday

        $timesheets = [
            $this->createTimesheet('2026-01-05', 8 * 3600), // Mon
            $this->createTimesheet('2026-01-06', 8 * 3600), // Tue
            $this->createTimesheet('2026-01-07', 8 * 3600), // Wed
            $this->createTimesheet('2026-01-08', 8 * 3600), // Thu
            $this->createTimesheet('2026-01-09', 8 * 3600), // Fri
        ];

        $result = $tool->checkWorkingTimeActToolGERCompliance(
            $timesheets,
            null, // No public holiday group
            $periodStart,
            $periodEnd
        );

        // Should work without public holidays
        $this->assertTrue($result['compliance']);
        $this->assertEquals(8.0, $result['average']);
        $this->assertEquals(40.0, $result['total_hours']);
        $this->assertEquals(5, $result['workdays']);
    }

    /**
     * Test with invalid period (start after end) should throw exception
     */
    public function testCheckComplianceWithInvalidPeriodThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Period start must be before period end');

        $periodStart = new DateTime('2026-01-16');
        $periodEnd = new DateTime('2026-01-05');

        $this->tool->checkWorkingTimeActToolGERCompliance(
            [],
            null,
            $periodStart,
            $periodEnd
        );
    }

    /**
     * Test with no timesheets (all empty days)
     */
    public function testCheckComplianceWithNoTimesheets(): void
    {
        $periodStart = new DateTime('2026-01-05'); // Monday
        $periodEnd = new DateTime('2026-01-09'); // Friday

        $this->publicHolidayRepository->expects($this->once())
            ->method('findHolidaysForPeriod')
            ->willReturn([]);

        $result = $this->tool->checkWorkingTimeActToolGERCompliance(
            [], // No timesheets
            1,
            $periodStart,
            $periodEnd
        );

        // 5 workdays, 0 hours
        // Average: 0 / 5 = 0 hours/day
        $this->assertTrue($result['compliance']);
        $this->assertEquals(0.0, $result['average']);
        $this->assertEquals(0.0, $result['total_hours']);
        $this->assertEquals(5, $result['workdays']);
    }

    /**
     * Helper method to create a mock timesheet
     */
    private function createTimesheet(string $date, int $durationSeconds): Timesheet
    {
        $timesheet = $this->createMock(Timesheet::class);

        $dateTime = new DateTime($date . ' 09:00:00');
        $timesheet->method('getBegin')->willReturn($dateTime);
        $timesheet->method('getDuration')->willReturn($durationSeconds);

        return $timesheet;
    }
}
