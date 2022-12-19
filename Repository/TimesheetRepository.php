<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use App\Repository\TimesheetRepository as CoreTimesheetRepository;
use App\Entity\User;
use DateTime;

class TimesheetRepository
{
    /**
     * @var CoreTimesheetRepository
     */
    private $timesheetRepository;

    /**
     * @param CoreTimesheetRepository $timesheetRepository
     */
    public function __construct(CoreTimesheetRepository $timesheetRepository)
    {
        $this->timesheetRepository = $timesheetRepository;
    }

    public function getActualDuration(User $user, DateTime $begin, DateTime $end): int
    {
        return intval($this->timesheetRepository->getStatistic($this->timesheetRepository::STATS_QUERY_DURATION, $begin, $end, $user));
    }
}
