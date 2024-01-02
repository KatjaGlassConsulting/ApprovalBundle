<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Repository\UserRepository;
use Doctrine\ORM\Exception\ORMException;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalOvertimeHistory;
use KimaiPlugin\ApprovalBundle\Form\AddOvertimeHistoryForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalOvertimeHistoryRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route(path: '/approval')]
class SettingsOvertimeController extends BaseApprovalController
{
    public function __construct(
        private SettingsTool $settingsTool,
        private ApprovalOvertimeHistoryRepository $approvalOvertimeHistoryRepository,
        private UserRepository $userRepository
    ) {
    }

    #[Route(path: '/settings_overtime', name: 'approval_settings_overtime_history', methods: ['GET', 'POST'])]
    #[IsGranted(new Expression("is_granted('view_team_approval') or is_granted('view_all_approval')"))]
    public function settingsOvertime(Request $request): Response
    {
        if (!$this->settingsTool->isOvertimeCheckActive()) {
            return $this->redirectToRoute('approval_bundle_report');
        }

        $overtimeHistory = $this->approvalOvertimeHistoryRepository->findAll();

        return $this->render('@Approval/settings_overtime_history.html.twig', [
            'current_tab' => 'settings_overtime_history',
            'overtimeHistory' => $overtimeHistory,
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }

    #[Route(path: '/create_overtime_history', name: 'approval_create_overtime_history', methods: ['GET', 'POST'])]
    public function createOvertimeHistory(Request $request): Response
    {
        $users = $this->userRepository->findAll();

        $form = $this->createForm(AddOvertimeHistoryForm::class, $users, [
            'action' => $this->generateUrl('approval_create_overtime_history'),
            'method' => 'POST'
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $overtimeHistory = new ApprovalOvertimeHistory();

                $overtimeHistory->setUserId($form->getData()['user']);
                $overtimeHistory->setDuration($form->getData()['duration']);
                $overtimeHistory->setApplyDate($form->getData()['applyDate']);

                $this->approvalOvertimeHistoryRepository->save($overtimeHistory, true);
                $this->flashSuccess('action.update.success');

                return $this->redirectToRoute('approval_settings_overtime_history');
            } catch (ORMException $e) {
                $this->flashUpdateException($e);
            }
        }

        return $this->render('@Approval/add_overtime_history.html.twig', [
            'title' => 'title.add_overtime_history',
            'form' => $form->createView()
        ]);
    }

    #[Route(path: '/deleteOvertimeHistory', name: 'delete_overtime_history', methods: ['GET'])]
    public function deleteOvertimeHistoryAction(Request $request): Response
    {
        $entryId = $request->get('entryId');

        $overtimeHistory = $this->approvalOvertimeHistoryRepository->find($entryId);
        if ($overtimeHistory) {
            $user = $overtimeHistory->getUser();

            $this->approvalOvertimeHistoryRepository->remove($overtimeHistory, true);
        }

        return $this->redirectToRoute('approval_settings_overtime_history');
    }
}
