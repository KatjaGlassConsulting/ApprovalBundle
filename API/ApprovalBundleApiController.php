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
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalStatusRepository;
use KimaiPlugin\ApprovalBundle\Repository\LockdownRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\EmailTool;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Swagger\Annotations as SWG;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @SWG\Tag(name="ApprovalBundleApi")
 */
final class ApprovalBundleApiController extends AbstractController
{
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var EmailTool
     */
    private $emailTool;
    /**
     * @var ViewHandlerInterface
     */
    private $viewHandler;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;
    /**
     * @var ApprovalHistoryRepository
     */
    private $approvalHistoryRepository;
    /**
     * @var AuthorizationCheckerInterface
     */
    private $security;
    /**
     * @var ApprovalStatusRepository
     */
    private $approvalStatusRepository;
    /**
     * @var TranslatorInterface
     */
    private $translator;
    /**
     * @var LockdownRepository
     */
    private $lockdownRepository;

    public function __construct(
        ViewHandlerInterface $viewHandler,
        UserRepository $userRepository,
        EmailTool $emailTool,
        UrlGeneratorInterface $urlGenerator,
        ApprovalRepository $approvalRepository,
        ApprovalHistoryRepository $approvalHistoryRepository,
        ApprovalStatusRepository $approvalStatusRepository,
        AuthorizationCheckerInterface $security,
        TranslatorInterface $translator,
        LockdownRepository $lockdownRepository
    ) {
        $this->viewHandler = $viewHandler;
        $this->userRepository = $userRepository;
        $this->emailTool = $emailTool;
        $this->urlGenerator = $urlGenerator;
        $this->approvalRepository = $approvalRepository;
        $this->approvalHistoryRepository = $approvalHistoryRepository;
        $this->security = $security;
        $this->approvalStatusRepository = $approvalStatusRepository;
        $this->translator = $translator;
        $this->lockdownRepository = $lockdownRepository;
    }

    /**
     * @SWG\Response(
     *     response=200,
     *     description="URL to submitted week"
     * )
     *
     * @Rest\Post(path="/add_to_approve")
     * @ApiSecurity(name="apiUser")
     * @ApiSecurity(name="apiToken")
     * @throws Exception
     */
    public function submitWeekAction(Request $request): Response
    {
        $selectedUserId = $request->query->get('user', -1);
        $selectedDate = $this->getSelectedDate($request);
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
        $startOfWeek = $selectedDate->format('Y-m-d');
        if ($nextApproveWeek && $nextApproveWeek < $startOfWeek) {
            return $this->error400($this->translator->trans('api.add_to_approve_previous_weeks'));
        }

        /** @var Approval|null $approval */
        $approval = $this->approvalRepository->findOneBy(['user' => $currentUser, 'startDate' => $selectedDate], ['creationDate' => 'DESC']);

        if ($approval !== null) {
            $history = $approval->getHistory();
            $status = $history[\count($history) - 1]->getStatus()->getName();
            if ($status !== ApprovalStatus::NOT_SUBMITTED) {
                return $this->error400($this->translator->trans('api.alreadyExists'));
            }
        }

        $approval = $this->approvalRepository->createApproval($selectedDate->format('Y-m-d'), $currentUser);
        if ($approval !== null) {
            $approval = $this->createHistory($approval);
            $this->emailTool->sendApproveWeekEmail($approval, $this->approvalRepository);
            $this->lockdownRepository->updateLockWeek($approval, $this->approvalRepository);
        }

        return $this->viewHandler->handle(
            new View(
                $this->urlGenerator->generate('approval_bundle_report', [
                    'user' => $approval->getUser()->getId(),
                    'date' => $approval->getStartDate()->format('Y-m-d')
                ], UrlGeneratorInterface::ABSOLUTE_URL),
                200
            )
        );
    }

    protected function getSelectedDate(Request $request): DateTime
    {
        $selectedDate = new DateTime($request->query->get('date'));
        if ($selectedDate->format('N') != 1) {
            $selectedDate->modify('previous Monday');
        }

        return $selectedDate;
    }

    protected function checkIfUserInTeam($user, $selectedUser): array
    {
        return array_filter(
            $user->getTeams(),
            function ($team) use ($selectedUser) {
                foreach ($team->getUsers() as $user) {
                    if ($user->getId() == $selectedUser) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    protected function error400(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 400)
        );
    }

    protected function error404(string $message): Response
    {
        return $this->viewHandler->handle(
            new View($message, 404)
        );
    }

    private function isGrantedViewAllApproval(): bool
    {
        return $this->security->isGranted('view_all_approval');
    }

    private function isGrantedViewTeamApproval(): bool
    {
        return $this->security->isGranted('view_team_approval');
    }

    private function createHistory(?Approval $approval): ?Approval
    {
        if ($approval) {
            if ($approval->getId() > 0) {
                $approval = $this->approvalRepository->find($approval->getId());
                $approveHistory = new ApprovalHistory();
                $approveHistory->setUser($this->userRepository->find($this->getUser()->getId()));
                $approveHistory->setApproval($approval);
                $approveHistory->setDate(new DateTime('now'));
                $approveHistory->setStatus($this->approvalStatusRepository->findOneBy(['name' => ApprovalStatus::SUBMITTED]));
                $history = $approval->getHistory();
                $history[] = $approveHistory;
                $approval->setHistory($history);
                $this->approvalHistoryRepository->persistFlush($approveHistory);
            }
        }

        return $approval;
    }
}
