<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Team;
use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;

class BaseApprovalController extends AbstractController
{
    protected function canManageTeam(): bool
    {
        return $this->isGranted('view_team_approval');
    }

    protected function canManageAllPerson(): bool
    {
        return $this->isGranted('view_all_approval');
    }

    protected function getDefaultTemplateParams(SettingsTool $settingsTool): array
    {
        return [
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $settingsTool->isOvertimeCheckActive(),
            'showOvertime' => $settingsTool->isOvertimeCheckActive()
        ];
    }

    protected function getOvertimeUsers(UserRepository $userRepository): array
    {
        if ($this->canManageAllPerson()) {
            $users = $userRepository->findAll();
        } elseif ($this->canManageTeam()) {
            $users = [];
            $user = $this->getUser();
            /** @var Team $team */
            foreach ($user->getTeams() as $team) {
                if (\in_array($user, $team->getTeamleads())) {
                    array_push($users, ...$team->getUsers());
                } else {
                    $users[] = $user;
                }
            }
            if (empty($users)) {
                $users = [$user];
            }
            $users = array_unique($users);
        } else {
            $users = [$this->getUser()];
        }

        $users = array_reduce($users, function ($current, $user) {
            if ($user->isEnabled() && !$user->isSuperAdmin()) {
                $current[] = $user;
            }

            return $current;
        }, []);

        if (!empty($users)) {
            usort(
                $users,
                function (User $userA, User $userB) {
                    return strcmp(strtoupper($userA->getUsername()), strtoupper($userB->getUsername()));
                }
            );
        }

        return $users;
    }
}
