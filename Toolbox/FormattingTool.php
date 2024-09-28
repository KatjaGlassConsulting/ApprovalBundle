<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use DateTime;
use Exception;
use Symfony\Contracts\Translation\TranslatorInterface;

final class FormattingTool
{
    public function __construct(private readonly TranslatorInterface $translator)
    {
    }

    /**
     * @param float|int $duration
     * @return int
     */
    public function formattingDurationToSeconds($duration): int
    {
        return (int) ($duration * 60 * 60);
    }

    /**
     * @param $number
     * @param int $precision
     * @return string
     */
    public function formattingNumber($number, $precision = 2): string
    {
        return number_format(round($number, $precision), $precision, $this->translator->trans('format.decimal_separator'), $this->translator->trans('format.thousands_separator'));
    }

    /**
     * @param $date
     * @return string
     * @throws Exception
     */
    public function formattingDate($date)
    {
        return $this->formattingDateOrDateTimeByPattern($date, $this->translator->trans('format.full_date'));
    }

    /**
     * @param $date
     * @return string
     * @throws Exception
     */
    public function formattingYearMonth($date)
    {
        $year = $this->formattingDateOrDateTimeByPattern($date, 'Y');
        $month = $this->formattingMonthIntToMonthString($this->formattingDateOrDateTimeByPattern($date, 'm'));

        return $year . ' ' . $month;
    }

    /**
     * @param $date
     * @return string
     * @throws Exception
     */
    public function formattingDatetime($date)
    {
        return $this->formattingDateOrDateTimeByPattern($date, $this->translator->trans('format.full_datetime'));
    }

    /**
     * @param $date
     * @param $pattern
     * @return string
     * @throws Exception
     */
    private function formattingDateOrDateTimeByPattern($date, $pattern)
    {
        if (\is_string($date)) {
            return (new DateTime($date))->format($pattern);
        }

        return $date->format($pattern);
    }

    /**
     * @param $month
     * @return string
     * @throws Exception
     */
    public function formattingMonthIntToMonthString($month)
    {
        $nameOfMonth = (new DateTime(\sprintf('01-%s-2020', $month)))->format('F');

        return $this->translator->trans('month.' . strtolower($nameOfMonth));
    }
}
