<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use App\Entity\Team;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Bundle\SecurityBundle\Security;

class SecurityTool
{
    private ?array $cache = null;
    private ?bool $viewAll = null;
    private ?bool $viewTeam = null;

    public function __construct(private Security $security, private UserRepository $userRepository)
    {
    }

    public function getUser(): ?User
    {
        $user = $this->security->getUser();

        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

    public function canViewAllApprovals(): bool
    {
        if ($this->viewAll === null) {
            $this->viewAll = $this->security->isGranted('view_all_approval');
        }

        return $this->viewAll;
    }

    public function canViewTeamApprovals(): bool
    {
        if ($this->viewTeam === null) {
            $this->viewTeam = $this->security->isGranted('view_team_approval');
        }

        return $this->viewTeam;
    }

    /**
     * @return array<User>
     */
    public function getUsers(): array
    {
        if ($this->cache === null) {
            /** @var User $user */
            $user = $this->security->getUser();

            if ($this->canViewAllApprovals()) {
                $users = $this->userRepository->findAll();
            } elseif ($this->canViewTeamApprovals()) {
                $users = [];
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
                $users = [$user];
            }

            /** @var array<User> $users */
            $users = array_reduce($users, function ($current, $user) {
                /** @var User $user */
                if ($user->isEnabled() && !$user->isSystemAccount() && !$user->isSuperAdmin()) {
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

            // this is a fallback, e.g. if the current user is a non-admin system-account
            if (\count($users) === 0) {
                $users = [$user];
            }

            $this->cache = $users;
        }

        return $this->cache;
    }
}
