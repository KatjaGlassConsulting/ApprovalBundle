<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\EmailTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kimai:bundle:approval:admin-not-submitted-users')]
class AdminNotSubmittedCommand extends Command
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private UserRepository $userRepository,
        private EmailTool $emailTool
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Sends emails to SUPER_ADMIN with users who have not yet submitted their status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->getUserList();

        $superAdmins = $this->userRepository->findUsersWithRole(User::ROLE_SUPER_ADMIN);
        $admins = $this->userRepository->findUsersWithRole(User::ROLE_ADMIN);
        $approvals = $this->approvalRepository->getAllNotSubmittedApprovals($users);

        if (!empty($approvals)) {
            $this->emailTool->sendAdminNotSubmittedEmail(
                $approvals,
                array_merge($superAdmins, $admins),
                $output
            );
        }

        return Command::SUCCESS;
    }

    private function getUserList(): array
    {
        return array_filter(
            $this->userRepository->findAll(),
            function ($user) {
                return !$user->isSuperAdmin() && $user->isEnabled();
            }
        );
    }
}
