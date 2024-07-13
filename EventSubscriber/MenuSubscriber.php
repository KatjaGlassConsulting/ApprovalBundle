<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\EventSubscriber;

use App\Event\ConfigureMainMenuEvent;
use App\Utils\MenuItemModel;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class MenuSubscriber implements EventSubscriberInterface
{
    private $approvalRepository;
    private $security;

    public function __construct(
        ApprovalRepository $approvalRepository,
        SecurityTool $security
    ) {
        $this->security = $security;
        $this->approvalRepository = $approvalRepository;
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

        $model = new MenuItemModel(
            'approvalBundle', 'title.approval_bundle', 'approval_bundle_report', [], 'fas fa-thumbs-up',
        );

        
        // do not do this for admins due to performance issues
        //if ($this->security->canViewAllApprovals() || $this->security->canViewTeamApprovals()) {
        if (($this->security->canViewAllApprovals() || $this->security->canViewTeamApprovals()) && 
             !$this->security->getUser()->isSuperAdmin()) {
            $users = $this->security->getUsers();
            $dataToMenuItem = $this->approvalRepository->findCurrentWeekToApprove($users, $currentUser);
            $model->setBadge((string) $dataToMenuItem);
            $model->setBadgeColor($dataToMenuItem === 0 ? 'green' : 'red');
        }

        $menu = $event->getMenu();
        $menu->addItem($model);
    }
}
