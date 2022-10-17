<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Command;

use App\Repository\UserRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\EmailTool;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class UserNotSubmittedCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'kimai:bundle:approval:user-not-submitted-weeks';
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
        UserRepository $userRepository,
        EmailTool $emailTool,
        ApprovalRepository $approvalRepository
    ) {
        parent::__construct();
        $this->userRepository = $userRepository;
        $this->emailTool = $emailTool;
        $this->approvalRepository = $approvalRepository;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command sends an email with a list of past "not submitted" weeks to the user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $users = $this->userRepository->findAll();
        $users = array_filter($users, function ($user) {
            return $user->isEnabled() && !$user->isSuperAdmin();
        });

        foreach ($users as $user) {
            $approvals = $this->approvalRepository->getAllNotSubmittedApprovals([$user]);
            if (!empty($approvals)) {
                $this->emailTool->sendUserNotSubmittedWeeksEmail($approvals, $user, $output);
            }
        }

        return 0;
    }
}
