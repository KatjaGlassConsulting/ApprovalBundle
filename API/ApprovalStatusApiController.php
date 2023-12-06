<?php

/*
 * This file is derived from Kimai2 version 1 using MIT license and now part of the ApprovalBundle Plugin
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
use FOS\RestBundle\Request\ParamFetcherInterface;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[OA\Tag(name: 'Approval')]
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
    #[Rest\QueryParam(name: 'user', requirements: '\d+', strict: true, nullable: true, description: 'User ID to get information for')]
    #[Rest\QueryParam(name: 'date', nullable: false, description: 'Date as monday of selected week: Y-m-d')]    
    #[Rest\Get(path: '/week-status')]
    #[ApiSecurity(name: 'apiUser')]
    #[ApiSecurity(name: 'apiToken')]
    public function submitWeekAction(ParamFetcherInterface $paramFetcher): Response
    {
        $selectedUserId = $paramFetcher->get('user');
        $selectedDate = $this->getSelectedDate($paramFetcher);
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

    protected function getSelectedDate(ParamFetcherInterface $paramFetcher): DateTime
    {
        $selectedDate = $paramFetcher->get('date');
        if ($selectedDate === null){
            $selectedDate = new DateTime('today');
        } else {
          $selectedDate = new DateTime($selectedDate);
        }
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
