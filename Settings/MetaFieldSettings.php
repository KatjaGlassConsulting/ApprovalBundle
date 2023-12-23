<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Settings;

use App\Entity\User;
use KimaiPlugin\MetaFieldsBundle\Repository\MetaFieldRuleRepository;

/**
 * @phpstan-ignore-next-line
 */
class MetaFieldSettings implements ApprovalSettingsInterface
{
    public function __construct(private MetaFieldRuleRepository $metaFieldRuleRepository)
    {
    }

    public function getRules(): array
    {
        return $this->metaFieldRuleRepository->getRules();
    }

    public function find($id)
    {
        return $this->metaFieldRuleRepository->find($id);
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
