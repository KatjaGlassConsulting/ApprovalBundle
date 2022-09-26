<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Settings;

use App\Entity\User;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;

class DefaultSettings implements ApprovalSettingsInterface
{
    public function canBeConfigured(): bool
    {
        return false;
    }

    public function isFullyConfigured(): bool
    {
        return true;
    }

    public function getRules(): array
    {
        return [];
    }

    public function find($id): mixed
    {
        return null;
    }

    public function getWorkingTimeForDay(User $user, string $name): int
    {
        return 0;
    }

    public function getWorkingTimeForMonday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_MONDAY);
    }

    public function getWorkingTimeForTuesday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_TUESDAY);
    }

    public function getWorkingTimeForWednesday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_WEDNESDAY);
    }

    public function getWorkingTimeForThursday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_THURSDAY);
    }

    public function getWorkingTimeForFriday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_FRIDAY);
    }

    public function getWorkingTimeForSaturday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SATURDAY);
    }

    public function getWorkingTimeForSunday(User $user): int
    {
        return $this->getWorkingTimeForDay($user, ConfigEnum::META_FIELD_EXPECTED_WORKING_TIME_ON_SUNDAY);
    }
}
