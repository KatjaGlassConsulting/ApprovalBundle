<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\EventSubscriber;

use App\Entity\Team;
use App\Entity\User;
use App\Event\ConfigureMainMenuEvent;
use App\Repository\UserRepository;
use App\Utils\MenuItemModel;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private TokenStorageInterface $token,
        private ApprovalRepository $approvalRepository,
        private AuthorizationCheckerInterface $security
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ConfigureMainMenuEvent::class => ['onMenuConfigure', 100],
        ];
    }

    public function onMenuConfigure(ConfigureMainMenuEvent $event): void
    {
        $currentUser = $this->getUser();
        if ($currentUser === null) {
            return;
        }

        $users = $this->getUsers();
        $dataToMenuItem = $this->approvalRepository->findCurrentWeekToApprove($users, $currentUser);

        $date = date('Y-m-d', strtotime('this week'));

        $isTeamLeadOrAdmin = $this->security->isGranted('view_all_approval') || $this->security->isGranted('view_team_approval');
        $menu = $event->getMenu();
        if (empty($users)) {
            $menu->addChild(
                new MenuItemModel(
                    'approvalBundle',
                    'title.approval_bundle',
                    'approval_bundle_settings',
                    [],
                    'fas fa-thumbs-up'
                )
            );
        } else {
            $model = new MenuItemModel(
                'approvalBundle',
                'title.approval_bundle',
                'approval_bundle_report',
                [
                    'user' => $users[0]->getId(),
                    'date' => $date
                ],
                'fas fa-thumbs-up',
            );

            if ($isTeamLeadOrAdmin) {
                $model->setBadge((string) $dataToMenuItem);
                $model->setBadgeColor($dataToMenuItem === 0 ? 'green' : 'red');
            }

            $menu->addChild($model);
        }
    }

    private function getUser(): ?User
    {
        $user = $this->token->getToken()->getUser();
        if ($user instanceof User) {
            return $user;
        }

        return null;
    }

    /**
     * @return array<User>
     */
    private function getUsers(): array
    {
        $user = $this->getUser();
        if ($this->security->isGranted('view_all_approval')) {
            $users = $this->userRepository->findAll();
        } elseif ($this->security->isGranted('view_team_approval')) {
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

        return array_reduce($users, function ($current, $user) {
            if ($user->isEnabled() && !$user->isSuperAdmin()) {
                $current[] = $user;
            }

            return $current;
        }, []);
    }
}
