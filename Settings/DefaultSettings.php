<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Settings;

use App\Entity\User;
use App\WorkingTime\WorkingTimeService;

class DefaultSettings implements ApprovalSettingsInterface
{
    public function __construct(private readonly WorkingTimeService $workingTimeService)
    {
    }

    public function getRules(): array
    {
        return [];
    }

    public function find($id): mixed
    {
        return null;
    }

    public function getWorkingTimeForDate(User $user, \DateTimeInterface $dateTime): int
    {
        return $this->workingTimeService->getContractMode($user)->getCalculator($user)->getWorkHoursForDay($dateTime);
    }
}
