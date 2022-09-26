<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Settings;

use App\Entity\User;

interface ApprovalSettingsInterface
{
    /**
     * Returns the list of fields to choose from.
     *
     * @return array
     */
    public function getRules(): array;

    /**
     * Find data for the given ID.
     *
     * @param string|int $id
     * @return mixed
     */
    public function find($id): mixed;

    /**
     * Whether this setting repository can be configured.
     *
     * @return bool
     */
    public function canBeConfigured(): bool;

    /**
     * Returns if the settings are fully configured.
     *
     * @return bool
     */
    public function isFullyConfigured(): bool;

    public function getWorkingTimeForMonday(User $user): int;

    public function getWorkingTimeForTuesday(User $user): int;

    public function getWorkingTimeForWednesday(User $user): int;

    public function getWorkingTimeForThursday(User $user): int;

    public function getWorkingTimeForFriday(User $user): int;

    public function getWorkingTimeForSaturday(User $user): int;

    public function getWorkingTimeForSunday(User $user): int;
}
