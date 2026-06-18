<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use App\Entity\Timesheet;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

class BreakTimeCheckToolGER
{
    public function __construct(private TranslatorInterface $translator, private SettingsTool $settingsTool)
    {
    }

    /**
     * @param array<Timesheet> $timesheets
     * @return array<string, array<int, string>>
     */
    public function checkBreakTime(array $timesheets): array
    {
        return $this->checkBreakTimeDetails($timesheets)['days'];
    }

    /**
     * @param array<Timesheet> $timesheets
     * @return array{days: array<string, array<int, string>>, timesheets: array<int, array<int, string>>}
     */
    public function checkBreakTimeDetails(array $timesheets): array
    {
        $errors = [
            'days' => [],
            'timesheets' => [],
        ];

        $customerId = (int) $this->settingsTool->getConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS, null);
        if ($customerId !== null) {
            $customerId = (int) $customerId;
        }
        $offdays = [];
        foreach ($timesheets as $timesheet) {
            if ($timesheet->getProject()->getCustomer()->getId() === $customerId) {
                $offdays[] = $timesheet->getBegin()->format('Y-m-d');
            }
        }
        $timesheets = array_filter(
            $timesheets,
            function (Timesheet $timesheet) use ($customerId) {
                return $timesheet->getProject()->getCustomer()->getId() !== $customerId;
            }
        );

        $this->checkSixHoursWithoutBreak($timesheets, $errors);
        $this->checkSixHoursAndBreak($timesheets, $errors);
        $this->checkNineHoursWithoutBreak($timesheets, $errors);
        $this->checkMoreThanTenHours($timesheets, $errors);
        $this->checkElevenHoursBreak($timesheets, $errors);
        $this->checkSundayWork($timesheets, $errors);
        $this->checkOffdayWork($timesheets, $errors, $offdays);

        return $this->addEmptyErrorsDays($timesheets, $errors);
    }

    private function checkOffdayWork(array $timesheets, array &$errors, array $offdays): void
    {
        foreach ($timesheets as $timesheet) {
            if (\in_array($timesheet->getBegin()->format('Y-m-d'), $offdays, true)) {
                $this->addError($errors, $timesheet, $this->translator->trans('error.work_offdays'));
            }
        }
    }

    private function checkSundayWork(array $timesheets, array &$errors): void
    {
        foreach ($timesheets as $timesheet) {
            if ($timesheet->getBegin()->format('w') == 0) {
                $this->addError($errors, $timesheet, $this->translator->trans('error.work_on_sunday'));
            }
        }
    }

    private function checkSixHoursWithoutBreak(array $timesheets, array &$errors): void
    {
        $sixHoursInSeconds = 6 * 60 * 60;
        $thirtyMinutesBreakInSeconds = 30 * 60;
        //'error.six_hours_without_stop_break'
        $lastDay = '0';
        $blockStart = 0;
        $blockEnd = 0;
        foreach ($timesheets as $timesheet) {
            if ($timesheet->getEnd() == null) {
                continue;
            }

            if ($lastDay != $timesheet->getBegin()->format('Y-m-d')) {
                $lastDay = $timesheet->getBegin()->format('Y-m-d');
                $blockStart = $timesheet->getBegin()->getTimestamp();
            } else {
                // if block-end (previous) + 30 mins >= new start -> set new block-start to current
                if ($blockEnd + $thirtyMinutesBreakInSeconds <= $timesheet->getBegin()->getTimestamp()) {
                    $blockStart = $timesheet->getBegin()->getTimestamp();
                }
            }
            $blockEnd = $timesheet->getEnd()->getTimestamp();
            if ($blockEnd - $blockStart > $sixHoursInSeconds) {
                $this->addError($errors, $timesheet, $this->translator->trans('error.six_hours_without_stop_break'));
            }
        }
    }

    private function checkSixHoursAndBreak(array $timesheets, array &$errors): void
    {
        $sixHoursInSeconds = 6 * 60 * 60;
        $thirtyMinutesBreakInSeconds = 30 * 60;

        $this->checkHoursBreak($sixHoursInSeconds, $thirtyMinutesBreakInSeconds, 'error.six_hours_without_break', $timesheets, $errors);
    }

    private function checkHoursBreak(int $hoursInSeconds, int $breakInSeconds, string $translationKey, array $timesheets, array &$errors): void
    {
        $minDurationForBreak = 15 * 60;
        $result = [];
        /** @var Timesheet $timesheet */
        foreach ($timesheets as $timesheet) {
            $hash = hash('sha512', ($timesheet->getUser()->getId() . $timesheet->getBegin()->format('Ymd')));

            if ($timesheet->getEnd()) {
                if (\array_key_exists($hash, $result)) {
                    if (\array_key_exists('lastEnd', $result[$hash])) {
                        $breakDuration = $timesheet->getBegin()->getTimestamp() - $result[$hash]['lastEnd']->getTimestamp();

                        $result[$hash]['duration'] += $timesheet->getDuration();
                        //if the break duration is minus the start point if before the last end point, this means there are two duration at the same time and no break!
                        //if the break it less than 15 minutes do not add it to break time because it is not enough for a break in Germany
                        if ($breakDuration < $minDurationForBreak) {
                            $breakDuration = 0;
                        }

                        $result[$hash]['breakDuration'] += $breakDuration;
                        $result[$hash]['lastEnd'] = $timesheet->getEnd();
                    }
                } else {
                    $result[$hash]['duration'] = $timesheet->getDuration();
                    $result[$hash]['breakDuration'] = 0;
                    $result[$hash]['lastEnd'] = $timesheet->getEnd();
                }

                if ($result[$hash]['duration'] > $hoursInSeconds) {
                    if ($result[$hash]['breakDuration'] < $breakInSeconds) {
                        $this->addError($errors, $timesheet, $this->translator->trans($translationKey));
                    }
                }
            }
        }
    }

    private function checkNineHoursWithoutBreak(array $timesheets, array &$errors): void
    {
        $nineHoursInSeconds = 9 * 60 * 60;
        $fortyFiveMinutesBreakInSeconds = 45 * 60;

        $this->checkHoursBreak($nineHoursInSeconds, $fortyFiveMinutesBreakInSeconds, 'error.nine_hours_without_break', $timesheets, $errors);
    }

    private function checkMoreThanTenHours(array $timesheets, array &$errors): void
    {
        $tenHoursInSeconds = 10 * 60 * 60;
        $result = [];
        /** @var Timesheet $timesheet */
        foreach ($timesheets as $timesheet) {
            $hash = hash('sha512', ($timesheet->getUser()->getId() . $timesheet->getBegin()->format('Ymd')));

            if (\array_key_exists($hash, $result)) {
                $result[$hash]['duration'] += $timesheet->getDuration();
            } else {
                $result[$hash]['duration'] = $timesheet->getDuration();
            }

            if ($result[$hash]['duration'] > $tenHoursInSeconds) {
                $this->addError($errors, $timesheet, $this->translator->trans('error.more_than_ten_hours_worked'));
            }
        }
    }

    private function checkElevenHoursBreak($timesheets, array &$errors)
    {
        $reduce = array_reduce(
            $timesheets,
            function ($result, Timesheet $timesheet) {
                if (\array_key_exists($timesheet->getUser()->getId(), $result)) {
                    $result[$timesheet->getUser()->getId()][] = $timesheet;
                } else {
                    $result[$timesheet->getUser()->getId()] = [$timesheet];
                }

                return $result;
            },
            []
        );
        foreach ($reduce as $value) {
            usort($value, function ($a, $b) {
                return $a->getBegin()->getTimestamp() - $b->getBegin()->getTimestamp();
            });

            for ($i = 0; $i < \count($value) - 1; $i++) {
                if ($value[$i]->getEnd() != null && $value[$i + 1]->getEnd() != null) {
                    $timesheetOne = $value[$i]->getEnd()->getTimestamp();
                    $timesheetTwo = $value[$i + 1]->getBegin()->getTimestamp();
                    if ($value[$i]->getEnd() == null) {
                        $this->addError($errors, $value[$i + 1], $this->translator->trans('error.no_end_date'));
                    }
                    if (abs($timesheetOne - $timesheetTwo) < 11 * 60 * 60 && $value[$i]->getEnd()->format('Y-m-d') < $value[$i + 1]->getEnd()->format('Y-m-d')) {    // 11h * 60 * 60 -> to seconds
                        $this->addError($errors, $value[$i + 1], $this->translator->trans('error.less_than_eleven_hours_off'));
                    }
                }
            }
        }
    }

    /**
     * @param array $timesheets
     * @param array $errors
     * @return array{days: array<string, array<int, string>>, timesheets: array<int, array<int, string>>}
     */
    protected function addEmptyErrorsDays(array $timesheets, array $errors): array
    {
        foreach ($timesheets as $timesheet) {
            $this->initializeErrorEntries($errors, $timesheet);
        }

        return $errors;
    }

    private function addError(array &$errors, Timesheet $timesheet, string $message): void
    {
        $this->initializeErrorEntries($errors, $timesheet);

        $date = $timesheet->getBegin()->format('Y-m-d');
        $timesheetId = spl_object_id($timesheet);

        if (!\in_array($message, $errors['days'][$date], true)) {
            $errors['days'][$date][] = $message;
        }

        if (!\in_array($message, $errors['timesheets'][$timesheetId], true)) {
            $errors['timesheets'][$timesheetId][] = $message;
        }
    }

    private function initializeErrorEntries(array &$errors, Timesheet $timesheet): void
    {
        $date = $timesheet->getBegin()->format('Y-m-d');
        $timesheetId = spl_object_id($timesheet);

        if (!\array_key_exists($date, $errors['days'])) {
            $errors['days'][$date] = [];
        }

        if (!\array_key_exists($timesheetId, $errors['timesheets'])) {
            $errors['timesheets'][$timesheetId] = [];
        }
    }
}
