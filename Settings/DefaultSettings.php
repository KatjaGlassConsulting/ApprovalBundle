<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Settings;

use App\Entity\User;

class DefaultSettings implements ApprovalSettingsInterface
{
    public function getRules(): array
    {
        return [];
    }

    public function find($id): mixed
    {
        return null;
    }

    public function getWorkingTimeForMonday(User $user): int
    {
        return $user->getWorkHoursMonday();
    }

    public function getWorkingTimeForTuesday(User $user): int
    {
        return $user->getWorkHoursTuesday();
    }

    public function getWorkingTimeForWednesday(User $user): int
    {
        return $user->getWorkHoursWednesday();
    }

    public function getWorkingTimeForThursday(User $user): int
    {
        return $user->getWorkHoursThursday();
    }

    public function getWorkingTimeForFriday(User $user): int
    {
        return $user->getWorkHoursFriday();
    }

    public function getWorkingTimeForSaturday(User $user): int
    {
        return $user->getWorkHoursSaturday();
    }

    public function getWorkingTimeForSunday(User $user): int
    {
        return $user->getWorkHoursSunday();
    }
}
