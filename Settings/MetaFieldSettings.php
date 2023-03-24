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
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\MetaFieldsBundle\Repository\MetaFieldRuleRepository;

/**
 * @phpstan-ignore-next-line
 */
class MetaFieldSettings implements ApprovalSettingsInterface
{
    private $metaFieldRuleRepository;
    private $settingsTool;

    public function __construct(MetaFieldRuleRepository $metaFieldRuleRepository, SettingsTool $settingsTool)
    {
        $this->metaFieldRuleRepository = $metaFieldRuleRepository;
        $this->settingsTool = $settingsTool;
    }

    public function canBeConfigured(): bool
    {
        return true;
    }

    public function isFullyConfigured(): bool
    {
        return $this->settingsTool->isAllSettingsUpdated();
    }

    public function getRules(): array
    {
        return $this->metaFieldRuleRepository->getRules();
    }

    public function find($id)
    {
        return $this->metaFieldRuleRepository->find($id);
    }

    public function getWorkingTimeForDay(User $user, string $name): int
    {
        $metaField = $this->metaFieldRuleRepository->find(
            $this->settingsTool->getConfiguration($name)
        );

        if ($metaField === null) {
            return 0;
        }

        if ($user->getPreferenceValue($metaField->getName()) === null) {
            return 0;
        }

        return $user->getPreferenceValue($metaField->getName());
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
