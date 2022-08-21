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
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class TeamleadNotSubmittedCommand extends Command
{
    // the name of the command (the part after "bin/console")
    protected static $defaultName = 'kimai:bundle:approval:teamlead-not-submitted-last-week';

    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;
    /**
     * @var EmailTool
     */
    private $emailTool;
    /**
     * @var TeamRepository
     */
    private $teamRepository;

    public function __construct(
        ApprovalRepository $approvalRepository,
        EmailTool $emailTool,
        TeamRepository $teamRepository
    ) {
        parent::__construct();
        $this->approvalRepository = $approvalRepository;
        $this->emailTool = $emailTool;
        $this->teamRepository = $teamRepository;
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command sends emails to the Team lead with a list of team users witch have "not submitted" status');
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

        return 0;
    }

    protected function getUsers(Team $team): array
    {
        return array_filter(
            $team->getUsers(),
            function ($user) {
                return $user->isEnabled() && !$user->isSuperAdmin();
            }
        );
    }

    protected function getTeamLeaders(Team $team): array
    {
        return array_filter(
            $team->getTeamleads(),
            function ($user) {
                return $user->isEnabled() && !$user->isSuperAdmin();
            }
        );
    }
}
