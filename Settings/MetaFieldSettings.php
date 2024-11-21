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
use KimaiPlugin\MetaFieldsBundle\Repository\MetaFieldRuleRepository;

/**
 * @phpstan-ignore-next-line
 */
class MetaFieldSettings implements ApprovalSettingsInterface
{
    public function __construct(
        private readonly MetaFieldRuleRepository $metaFieldRuleRepository,
        private readonly WorkingTimeService $workingTimeService
    ) {
    }

    public function getRules(): array
    {
        return $this->metaFieldRuleRepository->getRules();
    }

    public function find($id)
    {
        return $this->metaFieldRuleRepository->find($id);
    }

    public function getWorkingTimeForDate(User $user, \DateTimeInterface $dateTime): int
    {
        return $this->workingTimeService->getContractMode($user)->getCalculator($user)->getWorkHoursForDay($dateTime);
    }
}
