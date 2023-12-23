<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private SecurityTool $security
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
        $currentUser = $this->security->getUser();
        if ($currentUser === null) {
            return;
        }

        $users = $this->security->getUsers();
        $dataToMenuItem = $this->approvalRepository->findCurrentWeekToApprove($users, $currentUser);

        $date = date('Y-m-d', strtotime('this week'));

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
        }

        $model = new MenuItemModel(
            'approvalBundle',
            'title.approval_bundle',
            'approval_bundle_report',
            [
                //'user' => $users[0]->getId(),
                'date' => $date
            ],
            'fas fa-thumbs-up',
        );

        if ($this->security->canViewAllApprovals() || $this->security->canViewTeamApprovals()) {
            $model->setBadge((string) $dataToMenuItem);
            $model->setBadgeColor($dataToMenuItem === 0 ? 'green' : 'red');
        }

        $menu->addChild($model);
    }
}
