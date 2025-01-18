<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\API;

use App\API\BaseApiController;
use App\Repository\UserRepository;
use DateTime;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'ApprovalBundle')]
final class ApprovalStatusApiController extends BaseApiController
{
    public function __construct(
        private ViewHandlerInterface $viewHandler,
        private UserRepository $userRepository,
        private ApprovalRepository $approvalRepository,
        private AuthorizationCheckerInterface $security,
        private TranslatorInterface $translator
    ) {
    }

    #[OA\Response(response: 200, description: 'Status of selected week')]
    #[Rest\QueryParam(name: 'user', requirements: '\d+', strict: true, nullable: true, description: 'User ID to get information for')]
    #[Rest\QueryParam(name: 'date', nullable: false, description: 'Date as monday of selected week: Y-m-d')]
    #[Route(methods: ['GET'], path: '/approval-bundle/week-status')]
    public function submitWeekAction(Request $request): Response
    {
        $selectedUserId = $request->query->get('user');
        $selectedDate = $this->getSelectedDate($request);
        $currentUser = $this->userRepository->find($this->getUser()->getId());

        if ($selectedUserId !== null) {
            if (!$this->isGrantedViewAllApproval() && !$this->isGrantedViewTeamApproval()) {
                return $this->error400($this->translator->trans('api.accessDenied'));
            }
            if (
                !$this->isGrantedViewAllApproval() &&
                $this->isGrantedViewTeamApproval() &&
                empty($this->checkIfUserInTeam($currentUser, $selectedUserId))
            ) {
                return $this->error400($this->translator->trans('api.wrongTeam'));
            }
            $selectedUser = $this->userRepository->find($selectedUserId);
            if (!$selectedUser || !$selectedUser->isEnabled()) {
                return $this->error404($this->translator->trans('api.wrongUser'));
            }
            $currentUser = $selectedUser;
        }

        return $this->viewHandler->handle(
            new View(
                $this->translator->trans(
                    $this->getStatus($currentUser, $selectedDate)
                ),
                200
            )
        );
    }

    private function getSelectedDate(Request $request): DateTime
    {
        $selectedDate = new DateTime($request->query->get('date', 'today'));
        if ($selectedDate->format('N') != 1) {
            $selectedDate->modify('previous Monday');
        }

        return $selectedDate;
    }

    private function isGrantedViewAllApproval(): bool
    {
        return $this->security->isGranted('view_all_approval');
    }

    private function isGrantedViewTeamApproval(): bool
    {
        return $this->security->isGranted('view_team_approval');
    }

    private function error400(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 400)
        );
    }

    private function checkIfUserInTeam($user, $selectedUserId): array
    {
        return array_filter(
            $user->getTeams(),
            function ($team) use ($selectedUserId) {
                foreach ($team->getUsers() as $user) {
                    if ($user->getId() == $selectedUserId) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    private function error404(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 404)
        );
    }

    private function getStatus($currentUser, DateTime $selectedDate): string
    {
        $status = ApprovalStatus::NOT_SUBMITTED;

        /** @var Approval|null $approval */
        $approval = $this->approvalRepository->findOneBy(['user' => $currentUser, 'startDate' => $selectedDate], ['creationDate' => 'DESC']);
        if ($approval !== null) {
            $history = $approval->getHistory();
            $status = $history[\count($history) - 1]->getStatus()->getName();
        }

        return $status;
    }
}
