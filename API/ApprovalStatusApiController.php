<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\API;

use App\Repository\UserRepository;
use DateTime;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'ApprovalStatusApi')]
final class ApprovalStatusApiController extends AbstractController
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var ViewHandlerInterface
     */
    private $viewHandler;
    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;
    /**
     * @var AuthorizationCheckerInterface
     */
    private $security;
    /**
     * @var TranslatorInterface
     */
    private $translator;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        UserRepository $userRepository,
        ApprovalRepository $approvalRepository,
        AuthorizationCheckerInterface $security,
        TranslatorInterface $translator
    ) {
        $this->viewHandler = $viewHandler;
        $this->userRepository = $userRepository;
        $this->approvalRepository = $approvalRepository;
        $this->security = $security;
        $this->translator = $translator;
    }

    /**
     *
     * @throws Exception
     */
    #[OA\Response(
        response: 200,
        description: 'Status of selected week',
    )]
    #[OA\Parameter(
        name: 'user',
        in: 'query',
        type: 'integer',
        description: 'User ID to get information for',
        required: false,
    )]
    #[OA\Parameter(
        name: 'date',
        in: 'query',
        type: 'string',
        description: 'Date as monday of selected week: Y-m-d',
        required: true,
    )]
    #[Rest\Get(path: '/week-status')]
    #[ApiSecurity(name: 'apiUser')]
    #[ApiSecurity(name: 'apiToken')]
    public function submitWeekAction(Request $request): Response
    {
        $selectedUserId = $request->query->get('user', -1);
        $selectedDate = $this->getSelectedDate($request);
        $currentUser = $this->userRepository->find($this->getUser()->getId());

        if ($selectedUserId != -1) {
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

    protected function getSelectedDate(Request $request): DateTime
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

    protected function error400(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 400)
        );
    }

    protected function checkIfUserInTeam($user, $selectedUserId): array
    {
        return array_filter(
            $user->getTeams(),
            function ($team) use ($selectedUserId): bool {
                foreach ($team->getUsers() as $user) {
                    if ($user->getId() == $selectedUserId) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    protected function error404(string $message): Response
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
