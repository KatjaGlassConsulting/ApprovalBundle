<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use DateTime;
use Symfony\Contracts\Translation\TranslatorInterface;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;

class Formatting
{
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
    * @var SettingsTool
    */
    private $settingsTool;

    public function __construct(
        TranslatorInterface $translator,
        SettingsTool $settingsTool)
    {
        $this->translator = $translator;
        $this->settingsTool = $settingsTool;
    }

    public function parseDate(DateTime $dateTime)
    {
        $weekNumber = (clone $dateTime)->format('W');

        if (!$this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_WEEKSTART_SUNDAY_NY)) {
            if ((clone $dateTime)->format('D') === 'Mon') {
                $startWeekDay = (clone $dateTime)->format('d.m.Y');
            } else {
                $startWeekDay = (clone $dateTime)->modify('last monday')->format('d.m.Y');
            }
            $endWeekDay = (clone $dateTime)->modify('next sunday')->format('d.m.Y');
        }
        else {
            if ((clone $dateTime)->format('D') === 'Sun') {
                $startWeekDay = (clone $dateTime)->format('d.m.Y');
            } else {
                $startWeekDay = (clone $dateTime)->modify('last sunday')->format('d.m.Y');
            }
            $endWeekDay = (clone $dateTime)->modify('next saturday')->format('d.m.Y');
        }

        return (clone $dateTime)->format('F Y') . ' - ' . $this->translator->trans('label.week') . ' ' . $weekNumber . ' [' . $startWeekDay . ' - ' . $endWeekDay . ']';
    }

    public function formatDuration(int $duration) : string
    {
        $prefix = $duration < 0 ? "-" : "";
        $mins = abs($duration) / 60; 
        $hours = floor($mins / 60);
        $mins = $mins - ($hours * 60);
        $preZero = $mins < 9 ? "0" : "";
        return $prefix . $hours . ":" . $preZero . $mins;  
    }
}
