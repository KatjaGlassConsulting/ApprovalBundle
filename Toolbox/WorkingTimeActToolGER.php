<?php

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use DateTime;
use DateInterval;
use DateTimeInterface;
use App\Entity\User;
use KimaiPlugin\WorkContractBundle\Repository\PublicHolidayRepository;

/**
 * German Arbeitszeitgesetz (ArbZG) - Working Time Act compliance checker
 * 
 * § 3 ArbZG: "Die werktägliche Arbeitszeit der Arbeitnehmer darf acht Stunden nicht überschreiten. 
 * Sie kann auf bis zu zehn Stunden nur verlängert werden, wenn innerhalb von sechs Kalendermonaten 
 * oder innerhalb von 24 Wochen im Durchschnitt acht Stunden werktäglich nicht überschritten werden."
 * 
 * Translation: Daily working time must not exceed 8 hours. It can be extended to 10 hours only if 
 * within 6 calendar months or 24 weeks, the average daily working time does not exceed 8 hours.
 */
class WorkingTimeActToolGER
{
    private const MAX_DAILY_HOURS = 8;
    private const SECONDS_PER_HOUR = 3600;
    private const SUNDAY = 7;

    public function __construct(
        private ?PublicHolidayRepository $publicHolidayRepository = null
    ) {
    }

    /**
     * Check if timesheets comply with ArbZG regulations
     * 
     * @param array $timesheets Array of Timesheet entities
     * @param int|null $publicHolidayGroupId Public holiday group ID from user
     * @param DateTimeInterface $periodStart Start of period to check
     * @param DateTimeInterface $periodEnd End of period to check
     * 
     * @return array{
     *     'compliance': bool,
     *     'average': float,
     *     'total_hours': float,
     *     'workdays': int,
     *     'period_start': DateTimeInterface,
     *     'period_end': DateTimeInterface
     * }
     */
    public function checkWorkingTimeActToolGERCompliance(
        array $timesheets,
        ?int $publicHolidayGroupId,
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd
    ): array {
        // Validate input
        if ($periodStart > $periodEnd) {
            throw new \InvalidArgumentException('Period start must be before period end');
        }

        // Get all working days (excluding weekends and holidays)
        $workingDays = $this->getWorkingDaysInPeriod(
            $periodStart,
            $periodEnd,
            $publicHolidayGroupId
        );

        // Get daily hours grouped by date
        $dailyHours = $this->groupTimesheetsByDay($timesheets);

        // Calculate totals
        $totalHours = $this->sumHoursForWorkingDays($dailyHours, $workingDays);
        $workdayCount = count($workingDays);

        // Calculate average hours per working day
        $average = $workdayCount > 0 ? $totalHours / $workdayCount : 0;

        return [
            'compliance' => $average <= self::MAX_DAILY_HOURS,
            'average' => $average,
            'total_hours' => $totalHours,
            'workdays' => $workdayCount,
            'period_start' => $periodStart,
            'period_end' => $periodEnd
        ];
    }

    /**
     * Get all working days in a period (excludes weekends and public holidays)
     * 
     * @param DateTimeInterface $periodStart
     * @param DateTimeInterface $periodEnd
     * @param int|null $publicHolidayGroupId
     * @return array<string> Array of working dates in format 'Y-m-d'
     */
    private function getWorkingDaysInPeriod(
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        ?int $publicHolidayGroupId
    ): array {
        $allDays = $this->getAllDaysInPeriod($periodStart, $periodEnd);
        $publicHolidays = $this->fetchPublicHolidays($periodStart, $periodEnd, $publicHolidayGroupId);

        return array_filter($allDays, function (string $date) use ($publicHolidays) {
            return $this->isWorkingDay($date, $publicHolidays);
        });
    }

    /**
     * Get all days in a period (including weekends)
     * 
     * @param DateTimeInterface $start
     * @param DateTimeInterface $end
     * @return array<string> Array of dates in format 'Y-m-d'
     */
    private function getAllDaysInPeriod(DateTimeInterface $start, DateTimeInterface $end): array
    {
        $days = [];
        $current = (new DateTime())->setTimestamp($start->getTimestamp());
        $end = (new DateTime())->setTimestamp($end->getTimestamp());

        while ($current <= $end) {
            $days[] = $current->format('Y-m-d');
            $current->add(new DateInterval('P1D'));
        }

        return $days;
    }

    /**
     * Fetch public holidays for period from WorkContractBundle if available
     * 
     * @param DateTimeInterface $periodStart
     * @param DateTimeInterface $periodEnd
     * @param int|null $publicHolidayGroupId
     * @return array<string> Array of holiday dates in format 'Y-m-d'
     */
    private function fetchPublicHolidays(
        DateTimeInterface $periodStart,
        DateTimeInterface $periodEnd,
        ?int $publicHolidayGroupId
    ): array {
        if (!$publicHolidayGroupId || !$this->isWorkContractBundleAvailable()) {
            return [];
        }

        try {
            $holidays = $this->publicHolidayRepository->findHolidaysForPeriod(
                $periodStart,
                $periodEnd,
                $publicHolidayGroupId
            );

            return array_map(
                fn($holiday) => $holiday->getDate()->format('Y-m-d'),
                $holidays
            );
        } catch (\Exception $e) {
            // Log or handle error gracefully
            return [];
        }
    }

    /**
     * Check if a single date is a working day
     * 
     * @param string $date Date in format 'Y-m-d'
     * @param array<string> $publicHolidays Array of public holiday dates
     * @return bool True if working day
     */
    private function isWorkingDay(string $date, array $publicHolidays): bool
    {
        $dayOfWeek = (int) (new DateTime($date))->format('N');

        // Not a sunday
        if ($dayOfWeek === self::SUNDAY) {
            return false;
        }

        // Not a public holiday
        return !in_array($date, $publicHolidays, true);
    }

    /**
     * Group timesheets by day and sum hours
     * 
     * @param array $timesheets Array of Timesheet entities
     * @return array<string, float> ['YYYY-MM-DD' => hours]
     */
    private function groupTimesheetsByDay(array $timesheets): array
    {
        $dailyHours = [];

        foreach ($timesheets as $timesheet) {
            $dateStr = $this->extractDateFromTimesheet($timesheet);
            if ($dateStr === null) {
                continue;
            }

            $hours = $this->convertDurationToHours($timesheet->getDuration() ?? 0);
            $dailyHours[$dateStr] = ($dailyHours[$dateStr] ?? 0) + $hours;
        }

        return $dailyHours;
    }

    /**
     * Extract date from timesheet entity
     * 
     * @param mixed $timesheet Timesheet entity
     * @return string|null Date in format 'Y-m-d' or null if invalid
     */
    private function extractDateFromTimesheet($timesheet): ?string
    {
        $dateObj = $timesheet->getBegin() ?? $timesheet->getDate() ?? null;

        return $dateObj ? $dateObj->format('Y-m-d') : null;
    }

    /**
     * Convert duration in seconds to hours
     * 
     * @param int $durationSeconds Duration in seconds
     * @return float Duration in hours
     */
    private function convertDurationToHours(int $durationSeconds): float
    {
        return $durationSeconds / self::SECONDS_PER_HOUR;
    }

    /**
     * Sum hours for all working days (including empty days as 0 hours)
     * 
     * @param array<string, float> $dailyHours Grouped daily hours
     * @param array<string> $workingDays Working days in period
     * @return float Total hours worked
     */
    private function sumHoursForWorkingDays(array $dailyHours, array $workingDays): float
    {
        $totalHours = 0;

        foreach ($workingDays as $date) {
            $totalHours += $dailyHours[$date] ?? 0;
        }

        return $totalHours;
    }

    /**
     * Check if WorkContractBundle plugin is installed and available
     * 
     * @return bool True if bundle is available
     */
    private function isWorkContractBundleAvailable(): bool
    {
        return $this->publicHolidayRepository !== null &&
            class_exists('KimaiPlugin\WorkContractBundle\WorkContractBundle');
    }
}