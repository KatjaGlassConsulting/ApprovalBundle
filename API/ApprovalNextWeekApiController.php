<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\API;

use App\Entity\Team;
use App\Repository\UserRepository;
use Exception;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SWG\Tag(name="ApprovalNextWeekApi")
 */
final class ApprovalNextWeekApiController extends AbstractController
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
     * @SWG\Response(
     *     response=200,
     *     description="Status of selected week"
     * )
     *
     * @Rest\Get(path="/next-week")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     * @throws Exception
     */
    public function nextWeekAction(Request $request): Response
    {
        $selectedUserId = $request->query->get('user', -1);
        $currentUser = $this->userRepository->find($this->getUser()->getId());

        if ($selectedUserId !== -1) {
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

        $nextApproveWeek = $this->approvalRepository->getNextApproveWeek($currentUser);
        if ($nextApproveWeek) {
            return $this->viewHandler->handle(
                new View(
                    $this->translator->trans(
                        $nextApproveWeek
                    ),
                    200
                )
            );
        }

        return $this->error404($this->translator->trans('api.noData'));
    }

    private function isGrantedViewAllApproval(): bool
    {
        return $this->security->isGranted('view_all_approval');
    }

    private function isGrantedViewTeamApproval(): bool
    {
        return $this->security->isGranted('view_team_approval');
    }

    protected function error404(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 404)
        );
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
            function ($team) use ($selectedUserId) {
                /** @var Team $team */
                foreach ($team->getUsers() as $user) {
                    if ($user->getId() == $selectedUserId) {
                        return true;
                    }
                }

                return false;
            }
        );
    }
}
