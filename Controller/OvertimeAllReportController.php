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
use DateTime;
use App\Repository\UserRepository;
use Exception;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByAllForm;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/approval")
 */
class OvertimeAllReportController extends AbstractController
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
     * @Route(path="/overtime_by_all", name="overtime_all_report", methods={"GET","POST"})
     * @throws Exception
     */
    public function overtimeByUser(Request $request): Response
    {
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY) == false){
          return $this->redirectToRoute('approval_bundle_report');
        }

        $form = $this->createForm(OvertimeByAllForm::class);
        file_put_contents("C:/temp/blub.txt", "form - " . json_encode($form) . "\n", FILE_APPEND);
        file_put_contents("C:/temp/blub.txt", "formView - " . json_encode($form->createView()) . "\n", FILE_APPEND);

        $users = $this->getUsers();
        $weeklyEntries = $this->approvalRepository->getUserApprovals($users);
    
        // reduce the weekly Entries to only contain one enty per subject having the maximum date
        $reducedData = [];
        foreach ($weeklyEntries as $entry) {
            $userId = $entry['userId'];
            $endDate = $entry['endDate'];
            $user = $entry['user'];

            // Check if the userId already exists in the reducedData array
            if (!array_key_exists($userId, $reducedData) || $endDate > $reducedData[$userId]['endDate']) {
                $reducedData[$userId] = [
                    'userId' => $userId,
                    'endDate' => $endDate,
                    'user' => $user
                ];
            }
        }
        $reducedData = array_values($reducedData);
        usort($reducedData, function($a, $b) {
            return $a['user'] <=> $b['user'];
        });

        // include the overtime values for these dates
        foreach ($reducedData as &$entry) {
            $entry['overtime'] = $this->approvalRepository->getExpectedActualDurationsForYear(
                $this->userRepository->find($entry['userId']), 
                new DateTime($entry['endDate']));
        }

        return $this->render('@Approval/overtime_by_all.html.twig', [
            'current_tab' => 'overtime_by_user',
            'form' => $form->createView(),   
            'weeklyEntries' => $reducedData,
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
            if (empty($users)) {
                $users = [$user];
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
