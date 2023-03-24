<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Team;
use App\Entity\User;
use App\Reporting\WeekByUser;
use App\Repository\UserRepository;
use Exception;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Security;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByUserForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/overtime")
 */
class OvertimeReportController extends AbstractController
{
    private $settingsTool;
    private $approvalRepository;
    private $userRepository;

    public function __construct(
        SettingsTool $settingsTool,
        UserRepository $userRepository,
        ApprovalRepository $approvalRepository
    ) {
        $this->settingsTool = $settingsTool;
        $this->userRepository = $userRepository;
        $this->approvalRepository = $approvalRepository;
    }

    private function canManageTeam(): bool
    {
        return $this->isGranted('view_team_approval');
    }

    private function canManageAllPerson(): bool
    {
        return $this->isGranted('view_all_approval');
    }

    /** 
     * @Route(path="/overtime_by_user", name="overtime_bundle_report", methods={"GET","POST"})
     * @Security("is_granted('view_team_approval') or is_granted('view_all_approval') ")
     * @throws Exception
     */
    public function overtimeByUser(Request $request): Response
    {
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY) == false){
          return $this->redirectToRoute('approval_bundle_report');
        }

        $users = $this->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];

        $values = new WeekByUser();
        $values->setUser($firstUser);

        $form = $this->createForm(OvertimeByUserForm::class, $values, [
            'users' => $users,
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($firstUser);
        }

        $selectedUser = $values->getUser();
        $weeklyEntries = $this->approvalRepository->findAllWeekForUser($selectedUser,null);

        return $this->render('@Approval/overtime_by_user.html.twig', [
            'current_tab' => 'overtime_by_user',
            'form' => $form->createView(),
            'user' => $selectedUser,    
            'weeklyEntries' => array_reverse($weeklyEntries),
            'showToApproveTab' => $this->canManageAllPerson() || $this->canManageTeam(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)
        ]);
    }

    private function getUsers(): array
    {
        if ($this->canManageAllPerson()) {
            $users = $this->userRepository->findAll();
        } elseif ($this->canManageTeam()) {
            $users = [];
            $user = $this->getUser();
            /** @var Team $team */
            foreach ($user->getTeams() as $team) {
                if (\in_array($user, $team->getTeamleads())) {
                    array_push($users, ...$team->getUsers());
                } else {
                    $users[] = $user;
                }
            }
            $users = array_unique($users);
        } else {
            $users = [$this->getUser()];
        }

        $users = array_reduce($users, function ($current, $user) {
            if ($user->isEnabled() && !$user->isSuperAdmin()) {
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

        return $users;
    }
}
