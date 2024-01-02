<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Reporting\WeekByUser\WeekByUser;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByUserForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/approval')]
class OvertimeReportController extends BaseApprovalController
{
    public function __construct(
        private SettingsTool $settingsTool,
        private SecurityTool $securityTool,
        private ApprovalRepository $approvalRepository
    ) {
    }

    #[Route(path: '/overtime_by_user', name: 'overtime_bundle_report', methods: ['GET', 'POST'])]
    public function overtimeByUser(Request $request): Response
    {
        if (!$this->settingsTool->isOvertimeCheckActive()) {
            return $this->redirectToRoute('approval_bundle_report');
        }

        $users = $this->securityTool->getUsers();
        $firstUser = empty($users) ? $this->getUser() : $users[0];

        $values = new WeekByUser();
        $values->setUser($firstUser);

        $form = $this->createFormForGetRequest(OvertimeByUserForm::class, $values, [
            'users' => $users,
            'routePath' => $this->generateUrl('overtime_all_report'),
        ]);

        $form->submit($request->query->all(), false);

        if ($values->getUser() === null) {
            $values->setUser($firstUser);
        }

        $selectedUser = $values->getUser();
        $weeklyEntries = $this->approvalRepository->findAllWeekForUser($selectedUser, null);

        return $this->render('@Approval/overtime_by_user.html.twig', [
            'current_tab' => 'overtime_by_user',
            'form' => $form->createView(),
            'user' => $selectedUser,
            'weeklyEntries' => array_reverse($weeklyEntries),
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }
}
