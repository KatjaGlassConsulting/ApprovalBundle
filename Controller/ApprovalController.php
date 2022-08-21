<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Controller;

use App\Controller\AbstractController;
use App\Repository\UserRepository;
use DateTime;
use Exception;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalStatusRepository;
use KimaiPlugin\ApprovalBundle\Repository\LockdownRepository;
use KimaiPlugin\ApprovalBundle\Toolbox\EmailTool;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * @Route(path="/approval")
 */
class ApprovalController extends AbstractController
{
    /**
     * @var ApprovalRepository
     */
    private $approvalRepository;
    /**
     * @var ApprovalHistoryRepository
     */
    private $approvalHistoryRepository;
    /**
     * @var ApprovalStatusRepository
     */
    private $approvalStatusRepository;
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;
    /**
     * @var UserRepository
     */
    private $userRepository;
    /**
     * @var EmailTool
     */
    private $emailTool;
    /**
     * @var LockdownRepository
     */
    private $lockdownRepository;

    public function __construct(
        ApprovalRepository $approvalRepository,
        ApprovalHistoryRepository $approvalHistoryRepository,
        ApprovalStatusRepository $approvalStatusRepository,
        UrlGeneratorInterface $urlGenerator,
        UserRepository $userRepository,
        EmailTool $emailTool,
        LockdownRepository $lockdownRepository
    ) {
        $this->approvalRepository = $approvalRepository;
        $this->approvalHistoryRepository = $approvalHistoryRepository;
        $this->approvalStatusRepository = $approvalStatusRepository;
        $this->urlGenerator = $urlGenerator;
        $this->userRepository = $userRepository;
        $this->emailTool = $emailTool;
        $this->lockdownRepository = $lockdownRepository;
    }

    /**
     * @Route(path="/add_to_approve", name="add_to_approve", methods={"GET", "POST"})
     * @throws Exception
     */
    public function addToApprove(Request $request): RedirectResponse
    {
        $userId = $request->query->get('user');
        $date = $request->query->get('date');
        $approval = $this->createAddToApproveForm($userId, $date);
        $this->emailTool->sendApproveWeekEmail($approval, $this->approvalRepository);
        $this->lockdownRepository->updateLockWeek($approval, $this->approvalRepository);

        return new RedirectResponse($this->urlGenerator->generate('approval_bundle_report', [
            'user' => $userId,
            'date' => $date
        ]));
    }

    /**
     * @Route(path="/approve/{approveId}", defaults={"approveId": 0}, name="approve", methods={"GET"})
     * @throws Exception
     */
    public function approveAction(Request $request, string $approveId): RedirectResponse
    {
        $approval = $this->approvalRepository->find($approveId);
        $approval = $this->approvalRepository->checkLastStatus(
            $approval->getStartDate(),
            $approval->getEndDate(),
            $approval->getUser(),
            ApprovalStatus::SUBMITTED,
            $approval
        );
        if ($approval) {
            $approval = $this->createNewApproveHistory($approveId, ApprovalStatus::APPROVED);
            $this->emailTool->sendStatusChangedEmail(
                $approval,
                $this->getUser()->getUsername(),
                $this->approvalRepository->getUrl($approval->getUser()->getId(), $approval->getStartDate()->format('Y-m-d'))
            );
            $this->lockdownRepository->updateLockWeek($approval, $this->approvalRepository);
        }

        $date = $request->query->get('date');
        $this->emailIfClosedMonth($date);

        return new RedirectResponse($this->urlGenerator->generate('approval_bundle_report', [
            'user' => $request->query->get('user'),
            'date' => $request->query->get('date')
        ]));
    }

    /**
     * @Route(path="/not_approved/{approveId}", defaults={"approveId": 0}, name="not_approved", methods={"GET"})
     * @throws Exception
     */
    public function notApprovedAction(Request $request, string $approveId): RedirectResponse
    {
        $approval = $this->approvalRepository->find($approveId);
        $approval = $this->approvalRepository->checkLastStatus(
            $approval->getStartDate(),
            $approval->getEndDate(),
            $approval->getUser(),
            ApprovalStatus::APPROVED,
            $approval
        );
        if ($approval) {
            $approval = $this->createNewApproveHistory($approveId, ApprovalStatus::NOT_SUBMITTED);
            $this->lockdownRepository->updateLockWeek($approval, $this->approvalRepository);
        }

        return new RedirectResponse($this->urlGenerator->generate('approval_bundle_report', [
            'user' => $request->query->get('user'),
            'date' => $request->query->get('date')
        ]));
    }

    /**
     * @Route(path="/denied/{approveId}", defaults={"approveId": 0}, name="denied", methods={"GET"})
     * @throws Exception
     */
    public function deniedAction(Request $request, string $approveId): RedirectResponse
    {
        $approval = $this->approvalRepository->find($approveId);
        $approval = $this->approvalRepository->checkLastStatus(
            $approval->getStartDate(),
            $approval->getEndDate(),
            $approval->getUser(),
            ApprovalStatus::SUBMITTED,
            $approval
        );
        if ($approval) {
            $approval = $this->createNewApproveHistory($approveId, ApprovalStatus::DENIED, $request->query->get('input'));
            $this->emailTool->sendStatusChangedEmail(
                $approval,
                $this->getUser()->getUsername(),
                $this->approvalRepository->getUrl($approval->getUser()->getId(), $approval->getStartDate()->format('Y-m-d'))
            );
            $this->createNewApproveHistory($approveId, ApprovalStatus::NOT_SUBMITTED, '', (new DateTime())->modify('+2 second')->format('d.m.Y H:i:s'));
            $this->lockdownRepository->updateLockWeek($approval, $this->approvalRepository);
        }

        return new RedirectResponse($this->urlGenerator->generate('approval_bundle_report', [
            'user' => $request->query->get('user'),
            'date' => $request->query->get('date'),
        ]));
    }

    /**
     * @throws Exception
     */
    private function createAddToApproveForm($userId, $date): ?Approval
    {
        $user = $this->userRepository->find($userId);
        $approve = $this->approvalRepository->createApproval($date, $user);
        if ($approve) {
            $this->createNewApproveHistory($approve->getId(), ApprovalStatus::SUBMITTED);

            return $this->approvalRepository->find($approve->getId());
        } else {
            $startDate = new DateTime($date);
            $endDate = (clone $startDate)->modify('next sunday');

            return $this->approvalRepository->findOneBy(['startDate' => $startDate, 'endDate' => $endDate, 'user' => $user], ['id' => 'DESC']);
        }
    }

    /**
     * @throws Exception
     */
    private function createNewApproveHistory(string $approveId, string $status, string $message = null, string $dateTime = 'now'): ?Approval
    {
        if ($approveId > 0) {
            $dateTime = new DateTime($dateTime);
            $approve = $this->approvalRepository->find($approveId);
            $approveHistory = new ApprovalHistory();
            $approveHistory->setUser($this->getUser());
            $approveHistory->setApproval($approve);
            $approveHistory->setDate(clone $dateTime);
            $approveHistory->setStatus($this->approvalStatusRepository->findOneBy(['name' => $status]));
            $approveHistory->setMessage($message);
            $history = $approve->getHistory();
            $history[] = $approveHistory;
            $approve->setHistory($history);
            $this->approvalHistoryRepository->persistFlush($approveHistory);

            return $approve;
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function emailIfClosedMonth($date): void
    {
        $users = $this->userRepository->findAll();
        $users = array_filter($users, function ($user) {
            return $user->isEnabled() && !$user->isSuperAdmin();
        });
        if ($this->approvalRepository->areAllUsersApproved($date, $users)) {
            $this->emailTool->sendClosedWeekEmail((new DateTime($date))->format('F'));
        }
    }
}
