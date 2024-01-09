<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Controller\AbstractController;
use App\Reporting\WeekByUser;
use App\Repository\UserRepository;
use Exception;
use KimaiPlugin\ApprovalBundle\Enumeration\ConfigEnum;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByUserForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(path="/approval")
 */
class OvertimeReportController extends AbstractController
{
    private $settingsTool;
    private $securityTool;
    private $approvalRepository;

    public function __construct(
        SettingsTool $settingsTool,
        SecurityTool $securityTool,
        ApprovalRepository $approvalRepository
    ) {
        $this->settingsTool = $settingsTool;
        $this->securityTool = $securityTool;
        $this->approvalRepository = $approvalRepository;
    }

    /** 
     * @Route(path="/overtime_by_user", name="overtime_bundle_report", methods={"GET","POST"})
     * @throws Exception
     */
    public function overtimeByUser(Request $request): Response
    {
        if ($this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY) == false){
          return $this->redirectToRoute('approval_bundle_report');
        }

        $users = $this->securityTool->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];

        $values = new WeekByUser();
        $values->setUser($firstUser);

        $routePath = $this->generateUrl('overtime_all_report');
        $form = $this->createForm(OvertimeByUserForm::class, $values, [
            'users' => $users,
            'routePath' => $routePath,
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
            'showToApproveTab' => $this->securityTool->canViewAllApprovals() || $this->securityTool->canViewTeamApprovals(),
            'showSettings' => $this->isGranted('ROLE_SUPER_ADMIN'),
            'showSettingsWorkdays' => $this->isGranted('ROLE_SUPER_ADMIN') && $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY),
            'showOvertime' => $this->settingsTool->getConfiguration(ConfigEnum::APPROVAL_OVERTIME_NY)
        ]);
    }
}
