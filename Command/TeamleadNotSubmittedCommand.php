<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Command;

use App\Entity\Team;
use App\Repository\TeamRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\EmailTool;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'kimai:bundle:approval:teamlead-not-submitted-last-week')]
class TeamleadNotSubmittedCommand extends Command
{
    public function __construct(
        private ApprovalRepository $approvalRepository,
        private EmailTool $emailTool,
        private TeamRepository $teamRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setHelp('Sends emails to the Teamlead with a list of team users which did not yet submit their status');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $teams = $this->teamRepository->findAll();
        foreach ($teams as $team) {
            $users = $this->getUsers($team);
            $teamLeaders = $this->getTeamLeaders($team);
            $approvals = $this->approvalRepository->getAllNotSubmittedApprovals($users);
            if (!empty($approvals)) {
                $this->emailTool->sendTeamleadNotSubmittedEmail($approvals, $teamLeaders, $output);
            }
        }

        return Command::SUCCESS;
    }

    private function getUsers(Team $team): array
    {
        return array_filter(
            $team->getUsers(),
            function ($user) {
                return $user->isEnabled() && !$user->isSuperAdmin();
            }
        );
    }

    private function getTeamLeaders(Team $team): array
    {
        return array_filter(
            $team->getTeamleads(),
            function ($user) {
                return $user->isEnabled() && !$user->isSuperAdmin();
            }
        );
    }
}
