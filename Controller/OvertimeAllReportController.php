<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Repository\UserRepository;
use DateTime;
use KimaiPlugin\ApprovalBundle\Form\OvertimeByAllForm;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\SecurityTool;
use KimaiPlugin\ApprovalBundle\Toolbox\SettingsTool;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/approval')]
class OvertimeAllReportController extends BaseApprovalController
{
    public function __construct(
        private SettingsTool $settingsTool,
        private SecurityTool $securityTool,
        private UserRepository $userRepository,
        private ApprovalRepository $approvalRepository
    ) {
    }

    #[Route(path: '/overtime_by_all', name: 'overtime_all_report', methods: ['GET', 'POST'])]
    public function overtimeByUser(Request $request): Response
    {
        if (!$this->settingsTool->isOvertimeCheckActive()) {
            return $this->redirectToRoute('approval_bundle_report');
        }

        $form = $this->createForm(OvertimeByAllForm::class);
        $form->handleRequest($request);

        $selectedDate = new DateTime();
        if ($form->isSubmitted() && $form->isValid()) {
            $selectedDate = $form->get('date')->getData();
        }

        $users = $this->securityTool->getUsers();
        $weeklyEntries = $this->approvalRepository->getUserApprovals($users);

        // reduce the weekly Entries to only contain one enty per subject having the maximum date
        $reducedData = [];
        foreach ($weeklyEntries as $entry) {
            $userId = $entry['userId'];
            $endDate = $entry['endDate'];
            $user = $entry['user'];

            // Check if the userId already exists in the reducedData array
            if (!\array_key_exists($userId, $reducedData) || $endDate > $reducedData[$userId]['endDate']) {
                $reducedData[$userId] = [
                    'userId' => $userId,
                    'endDate' => $endDate,
                    'user' => $user
                ];
            }
        }
        $reducedData = array_values($reducedData);
        usort($reducedData, function ($a, $b) {
            return $a['user'] <=> $b['user'];
        });

        // include the overtime values for these dates
        foreach ($reducedData as &$entry) {
            $entry['overtime'] = $this->approvalRepository->getExpectedActualDurationsForYear(
                $this->userRepository->find($entry['userId']),
                new DateTime($entry['endDate'])
            );
            $entry['overtimeDate'] = $this->approvalRepository->getExpectedActualDurationsForYear(
                $this->userRepository->find($entry['userId']),
                $selectedDate
            );
        }

        return $this->render('@Approval/overtime_by_all.html.twig', [
            'current_tab' => 'overtime_by_user',
            'form' => $form->createView(),
            'weeklyEntries' => $reducedData,
            'selectedDate' => $selectedDate->format('Y-m-d'),
        ] + $this->getDefaultTemplateParams($this->settingsTool));
    }
}
