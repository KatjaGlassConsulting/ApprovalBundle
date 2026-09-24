<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Tests\Repository;

use App\Entity\User;
use App\Tests\KernelTestTrait;
use App\WorkingTime\Mode\WorkingTimeModeDay;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalWorkdayHistory;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Tests\DataFixtures\ApprovalUserFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * documentation.md, section "Overtime display" / "Calculate expected hours for a week":
 *
 *   "- if there is a Settings workdays entry for that user and the day is within this range -
 *      use the corresponding hour from there
 *    - if there are no settings or the date-range is not covered [...] the value from the
 *      meta-fields are used"
 *
 * Without the MetaFieldsBundle the second source is DefaultSettings, which asks Kimai's
 * WorkingTimeService - i.e. the per-weekday working hours configured on the user.
 *
 * @group integration
 */
class ExpectedWorkingTimeTest extends KernelTestCase
{
    use KernelTestTrait;

    private const HOURS_FROM_USER_CONTRACT = 8;
    private const HOURS_FROM_WORKDAY_SETTINGS = 5;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        [$this->user] = $this->importFixture(new ApprovalUserFixtures());

        // "meta field" side of the calculation: 8 hours on every working day
        $this->user->setWorkContractMode(WorkingTimeModeDay::ID);
        $this->user->setWorkHoursMonday(self::HOURS_FROM_USER_CONTRACT * 3600);
        $this->user->setWorkHoursTuesday(self::HOURS_FROM_USER_CONTRACT * 3600);
        $this->user->setWorkHoursWednesday(self::HOURS_FROM_USER_CONTRACT * 3600);
        $this->user->setWorkHoursThursday(self::HOURS_FROM_USER_CONTRACT * 3600);
        $this->user->setWorkHoursFriday(self::HOURS_FROM_USER_CONTRACT * 3600);
        $this->user->setWorkHoursSaturday(0);
        $this->user->setWorkHoursSunday(0);

        $this->getEntityManager()->persist($this->user);
        $this->getEntityManager()->flush();
    }

    private function repository(): ApprovalRepository
    {
        /** @var ApprovalRepository $repository */
        $repository = self::getContainer()->get(ApprovalRepository::class);

        return $repository;
    }

    /**
     * One "Settings workdays" row: 5 hours Monday to Friday, valid until the given day.
     */
    private function createWorkdaySettings(string $validTill): void
    {
        $history = new ApprovalWorkdayHistory();
        $history->setUserId($this->user);
        $history->setMonday(self::HOURS_FROM_WORKDAY_SETTINGS * 3600);
        $history->setTuesday(self::HOURS_FROM_WORKDAY_SETTINGS * 3600);
        $history->setWednesday(self::HOURS_FROM_WORKDAY_SETTINGS * 3600);
        $history->setThursday(self::HOURS_FROM_WORKDAY_SETTINGS * 3600);
        $history->setFriday(self::HOURS_FROM_WORKDAY_SETTINGS * 3600);
        $history->setSaturday(0);
        $history->setSunday(0);
        $history->setValidTill(new \DateTime($validTill));

        $this->getEntityManager()->persist($history);
        $this->getEntityManager()->flush();
    }

    private function expectedFor(string $start, string $end): int
    {
        return $this->repository()->calculateExpectedDurationByUserAndDate(
            $this->user,
            new \DateTime($start),
            new \DateTime($end)
        );
    }

    public function testWithoutWorkdaySettingsTheUserContractIsUsed(): void
    {
        // 2024-08-26 (Mon) until 2024-09-01 (Sun): five working days
        self::assertSame(5 * self::HOURS_FROM_USER_CONTRACT * 3600, $this->expectedFor('2024-08-26', '2024-09-01'));
    }

    public function testWorkdaySettingsOverrideTheUserContractWithinTheirValidity(): void
    {
        $this->createWorkdaySettings('2024-09-01');

        self::assertSame(5 * self::HOURS_FROM_WORKDAY_SETTINGS * 3600, $this->expectedFor('2024-08-26', '2024-09-01'));
    }

    public function testDaysAfterTheValidityFallBackToTheUserContract(): void
    {
        $this->createWorkdaySettings('2024-08-30');

        // Monday 2024-09-02 is no longer covered by the workday settings
        self::assertSame(
            self::HOURS_FROM_USER_CONTRACT * 3600,
            $this->expectedFor('2024-09-02', '2024-09-02')
        );
    }

    public function testAWeekCanMixBothSources(): void
    {
        // valid till Wednesday: Mon-Wed come from the settings, Thu/Fri from the contract
        $this->createWorkdaySettings('2024-08-28');

        self::assertSame(
            (3 * self::HOURS_FROM_WORKDAY_SETTINGS + 2 * self::HOURS_FROM_USER_CONTRACT) * 3600,
            $this->expectedFor('2024-08-26', '2024-09-01')
        );
    }

    public function testWeekendDaysDoNotAddExpectedTime(): void
    {
        $this->createWorkdaySettings('2024-09-01');

        // Saturday 2024-08-31 and Sunday 2024-09-01
        self::assertSame(0, $this->expectedFor('2024-08-31', '2024-09-01'));
    }

    public function testTheNewestMatchingWorkdaySettingsRowWins(): void
    {
        // an older row that ends earlier must not shadow the day it no longer covers
        $this->createWorkdaySettings('2024-08-28');
        $this->createWorkdaySettings('2100-01-01');

        // Thursday 2024-08-29 is only covered by the second row, which has the same hours
        self::assertSame(
            self::HOURS_FROM_WORKDAY_SETTINGS * 3600,
            $this->expectedFor('2024-08-29', '2024-08-29')
        );
    }
}
