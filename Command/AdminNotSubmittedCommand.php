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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AdminNotSubmittedCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'kimai:bundle:approval:admin-not-submitted-users';

    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var EmailTool
     */
    private $emailTool;

    public function __construct(
        ApprovalRepository $approvalRepository,
        UserRepository $userRepository,
        EmailTool $emailTool
    ) {
        parent::__construct();
        $this->approvalRepository = $approvalRepository;
        $this->userRepository = $userRepository;
        $this->emailTool = $emailTool;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command sends emails to SUPER_ADMIN with users who have "not submitted" status');
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

        return \Symfony\Component\Console\Command\Command::SUCCESS;
    }

    protected function getUserList(): array
    {
        return array_filter(
            $this->userRepository->findAll(),
            function ($user): bool {
                return !$user->isSuperAdmin() && $user->isEnabled();
            }
        );
    }
}
