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
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class SecurityTool
{
    private ?array $cacheUsers = null;
    private ?bool $viewAll = null;
    private ?bool $viewTeam = null;
    private ?User $user = null;
    
    public function __construct(
        private UserRepository $userRepository, 
        private TokenStorageInterface $token,
        private AuthorizationCheckerInterface $security)
    {    
    }
    
    public function getUser(): ?User
    {
        if ($this->user === null) {
            $this->user = $this->token->getToken()->getUser();

            if (!($this->user instanceof User)) {
                $this->user = null;
            }
        }

        return $this->user;        
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
    public function getUsersExcludeOwnWhenTeamlead(): array
    {
        $users = $this->getUsers();

        if ($this->canViewAllApprovals()) {
            return $users;
        } elseif ($this->canViewTeamApprovals()) {
            // remove the active user from the list
            $index = array_search($this->getUser(), $users);
            if ($index !== false) {
                unset($users[$index]);
            }
        }

        return $users;
    }

    /**
     * @return array<User>
     */
    public function getUsers(): array
    {
        if ($this->cacheUsers === null) {
            /** @var User $user */
            $user = $this->token->getToken()->getUser();

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

            $this->cacheUsers = $users;
        }

        return $this->cacheUsers;
    }
}
