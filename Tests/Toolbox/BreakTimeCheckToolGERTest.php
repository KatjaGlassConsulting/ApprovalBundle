<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Toolbox;

use App\Entity\Customer;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Covers the rules listed in documentation.md, section "Calculate Breaktime Issues":
 *
 *   - Users must not work more than 6 hours without a 30 minute break
 *   - Users must not work more than 9 hours without a 45 minute break
 *   - Users must not work more than 10 hours a day
 *   - User must rest at least 11 hours between days
 *   - "When the corresponding days belong to Days off, then the issues are not displayed for that day"
 *
 * The translator is mocked to return the message key, so the assertions read like the rule names.
 *
 * @covers \KimaiPlugin\ApprovalBundle\Toolbox\BreakTimeCheckToolGER
 */
class BreakTimeCheckToolGERTest extends TestCase
{
    private const USER_ID = 1;
    private const CUSTOMER_ID = 1;
    private const FREE_DAYS_CUSTOMER_ID = 99;

    private const MONDAY = '2024-08-26';
    private const TUESDAY = '2024-08-27';
    private const SUNDAY = '2024-09-01';

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = new User();
        self::setId($this->user, self::USER_ID);
    }

    /**
     * @param int|null $freeDaysCustomerId configured as "customer for free days"
     */
    private function createSut(?int $freeDaysCustomerId = null): BreakTimeCheckToolGER
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $settingsTool = $this->createMock(SettingsTool::class);
        $settingsTool->method('getConfiguration')->willReturn($freeDaysCustomerId);

        return new BreakTimeCheckToolGER($translator, $settingsTool);
    }

    private function createTimesheet(string $begin, string $end, int $customerId = self::CUSTOMER_ID): Timesheet
    {
        $customer = new Customer('Customer ' . $customerId);
        self::setId($customer, $customerId);

        $project = new Project();
        $project->setName('Project ' . $customerId);
        $project->setCustomer($customer);

        $timesheet = new Timesheet();
        $timesheet->setUser($this->user);
        $timesheet->setProject($project);
        $timesheet->setBegin(new \DateTime($begin));
        $timesheet->setEnd(new \DateTime($end));
        // setEnd() does not recalculate, but the rules rely on the stored duration
        $timesheet->setDuration($timesheet->getCalculatedDuration());

        return $timesheet;
    }

    private static function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }

    public function testACompliantDayProducesNoErrors(): void
    {
        // 6.5 hours of work interrupted by exactly the required 30 minute break
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 12:00'),
            $this->createTimesheet(self::MONDAY . ' 12:30', self::MONDAY . ' 15:00'),
        ]);

        self::assertSame([self::MONDAY => []], $errors);
    }

    public function testMoreThanSixHoursWithoutABreakIsReported(): void
    {
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 15:00'),
        ]);

        self::assertSame([
            self::MONDAY => [
                'error.six_hours_without_stop_break',
                'error.six_hours_without_break',
            ],
        ], $errors);
    }

    public function testAnInterruptionBelowFifteenMinutesDoesNotCountAsBreak(): void
    {
        // 6h50 of work, but the pause between the two records is only 10 minutes
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 12:00'),
            $this->createTimesheet(self::MONDAY . ' 12:10', self::MONDAY . ' 15:00'),
        ]);

        self::assertSame([
            self::MONDAY => [
                'error.six_hours_without_stop_break',
                'error.six_hours_without_break',
            ],
        ], $errors);
    }

    public function testMoreThanSixHoursInOneStretchIsReportedDespiteEnoughBreakTime(): void
    {
        // 1h + 30 min break + 6.5h in one stretch: the day has its 30 minute break,
        // but the second block exceeds six hours without interruption
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 07:00', self::MONDAY . ' 08:00'),
            $this->createTimesheet(self::MONDAY . ' 08:30', self::MONDAY . ' 15:00'),
        ]);

        self::assertSame([self::MONDAY => ['error.six_hours_without_stop_break']], $errors);
    }

    public function testABreakRuleIsReportedOnlyOncePerDay(): void
    {
        // both records push the day over six hours without enough break time,
        // but the message must appear only once
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 14:30'),
            $this->createTimesheet(self::MONDAY . ' 14:40', self::MONDAY . ' 16:00'),
        ]);

        self::assertSame([
            self::MONDAY => [
                'error.six_hours_without_stop_break',
                'error.six_hours_without_break',
            ],
        ], $errors);
    }

    public function testMoreThanNineHoursNeedsAFortyFiveMinuteBreak(): void
    {
        // 9.5 hours with a 30 minute break: enough for the six hour rule, not for the nine hour rule
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 06:00', self::MONDAY . ' 12:00'),
            $this->createTimesheet(self::MONDAY . ' 12:30', self::MONDAY . ' 16:00'),
        ]);

        self::assertSame([self::MONDAY => ['error.nine_hours_without_break']], $errors);
    }

    public function testMoreThanTenHoursADayIsReported(): void
    {
        // 11.5 hours in one record breaks all three duration rules at once
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 06:00', self::MONDAY . ' 17:30'),
        ]);

        self::assertSame([
            self::MONDAY => [
                'error.six_hours_without_stop_break',
                'error.six_hours_without_break',
                'error.nine_hours_without_break',
                'error.more_than_ten_hours_worked',
            ],
        ], $errors);
    }

    public function testLessThanElevenHoursRestBetweenTwoDaysIsReported(): void
    {
        // Monday ends at 22:00, Tuesday starts at 06:00 -> only 8 hours of rest
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 18:00', self::MONDAY . ' 22:00'),
            $this->createTimesheet(self::TUESDAY . ' 06:00', self::TUESDAY . ' 08:00'),
        ]);

        self::assertSame(['error.less_than_eleven_hours_off'], $errors[self::TUESDAY]);
        self::assertSame([], $errors[self::MONDAY]);
    }

    public function testARunningTimesheetFollowedByAnotherOneIsReported(): void
    {
        // Monday was never stopped, but Tuesday already has a new record
        $running = $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 09:00');
        $running->setEnd(null);
        $running->setDuration(0);

        $errors = $this->createSut()->checkBreakTime([
            $running,
            $this->createTimesheet(self::TUESDAY . ' 09:00', self::TUESDAY . ' 12:00'),
        ]);

        self::assertSame(['error.no_end_date'], $errors[self::TUESDAY]);
        self::assertSame([], $errors[self::MONDAY]);
    }

    public function testWorkingOnASundayIsReported(): void
    {
        $errors = $this->createSut()->checkBreakTime([
            $this->createTimesheet(self::SUNDAY . ' 09:00', self::SUNDAY . ' 11:00'),
        ]);

        self::assertSame([self::SUNDAY => ['error.work_on_sunday']], $errors);
    }

    public function testDaysOffAreExcludedFromTheChecks(): void
    {
        // 12 hours booked on the "customer for free days" - the day must not be reported at all,
        // while the regular Tuesday is still checked
        $errors = $this->createSut(self::FREE_DAYS_CUSTOMER_ID)->checkBreakTime([
            $this->createTimesheet(self::MONDAY . ' 08:00', self::MONDAY . ' 20:00', self::FREE_DAYS_CUSTOMER_ID),
            $this->createTimesheet(self::TUESDAY . ' 09:00', self::TUESDAY . ' 12:00'),
        ]);

        self::assertArrayNotHasKey(self::MONDAY, $errors);
        self::assertSame([self::TUESDAY => []], $errors);
    }

    public function testWithoutTimesheetsThereAreNoErrors(): void
    {
        self::assertSame([], $this->createSut()->checkBreakTime([]));
    }
}
