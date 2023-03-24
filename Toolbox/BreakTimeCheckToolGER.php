<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use App\Entity\Timesheet;
use Exception;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use Symfony\Contracts\Translation\TranslatorInterface;

class BreakTimeCheckToolGER
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var SettingsTool
     */
    private $settingsTool;

    public function __construct(TranslatorInterface $translator, SettingsTool $settingsTool)
    {
        $this->translator = $translator;
        $this->settingsTool = $settingsTool;
    }

    /**
     * @throws Exception
     */
    public function checkBreakTime(array $timesheets)
    {
        $errors = [];

        $customerId = $this->settingsTool->getConfiguration(ConfigEnum::CUSTOMER_FOR_FREE_DAYS);
        $timesheets = array_filter(
            $timesheets,
            function (Timesheet $timesheet) use ($customerId) {
                return $timesheet->getProject()->getCustomer()->getId() != $customerId;
            }
        );

        $this->checkSixHoursWithoutBreak($timesheets, $errors);
        $this->checkNineHoursWithoutBreak($timesheets, $errors);
        $this->checkMoreThanTenHours($timesheets, $errors);
        $this->checkElevenHoursBreak($timesheets, $errors);

        return $this->addEmptyErrorsDays($timesheets, $errors);
    }

    private function checkSixHoursWithoutBreak($timesheets, &$errors)
    {
        $sixHoursInSeconds = 6 * 60 * 60;
        $thirtyMinutesBreakInSeconds = 30 * 60;

        $this->checkHoursBreak($sixHoursInSeconds, $thirtyMinutesBreakInSeconds, 'error.six_hours_without_break', $timesheets, $errors);
    }

    private function checkHoursBreak($hoursInSeconds, $breakInSeconds, $translationKey, $timesheets, &$errors)
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
                        $errors[$timesheet->getBegin()->format('Y-m-d')][] = $this->translator->trans($translationKey);
                    }
                }
            }
        }
    }

    private function checkNineHoursWithoutBreak($timesheets, &$errors)
    {
        $nineHoursInSeconds = 9 * 60 * 60;
        $fortyFiveMinutesBreakInSeconds = 45 * 60;

        $this->checkHoursBreak($nineHoursInSeconds, $fortyFiveMinutesBreakInSeconds, 'error.nine_hours_without_break', $timesheets, $errors);
    }

    private function checkMoreThanTenHours($timesheets, &$errors)
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
                $errors[$timesheet->getBegin()->format('Y-m-d')][] = $this->translator->trans('error.more_than_ten_hours_worked');
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
                    $result[$timesheet->getUser()->getId()] = [];
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
                if ($value[$i]->getEnd() != null){
                    $timesheetOne = $value[$i]->getEnd()->getTimestamp();
                    $timesheetTwo = $value[$i + 1]->getBegin()->getTimestamp();
                    if ($value[$i]->getEnd() == null) {
                        $errors[$value[$i + 1]->getBegin()->format('Y-m-d')][] = $this->translator->trans('error.no_end_date');
                    }
                    if (abs($timesheetOne - $timesheetTwo) < 11 * 60 * 60 && $value[$i]->getEnd()->format('Y-m-d') < $value[$i + 1]->getEnd()->format('Y-m-d')) {    // 11h * 60 * 60 -> to seconds
                        $errors[$value[$i + 1]->getBegin()->format('Y-m-d')][] = $this->translator->trans('error.less_than_eleven_hours_off');
                    }
                }
            }
        }
    }

    /**
     * @param array $timesheets
     * @param array $errors
     * @return array
     */
    protected function addEmptyErrorsDays(array $timesheets, array $errors): array
    {
        foreach ($timesheets as $timesheet) {
            if (!\array_key_exists($timesheet->getBegin()->format('Y-m-d'), $errors)) {
                $errors[$timesheet->getBegin()->format('Y-m-d')] = [];
            }
        }

        return $errors;
    }
}
