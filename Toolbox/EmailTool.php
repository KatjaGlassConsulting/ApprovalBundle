<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Toolbox;

use App\Entity\User;
use App\Mail\KimaiMailer;
use App\Repository\UserRepository;
use KimaiPlugin\ApprovalBundle\Entity\Approval;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final class EmailTool
{
    public function __construct(
        private TranslatorInterface $translator,
        private KimaiMailer $kimaiMailer,
        private UserRepository $userRepository,
        private Formatting $formatting
    ) {
    }

    public function sendStatusChangedEmail(Approval $approval, string $approver, string $url): bool
    {
        $history = $approval->getHistory();
        $history = $history[\count($history) - 1];
        $status = $history->getStatus()->getName();
        $week = $this->formatting->parseDate(clone $approval->getStartDate());
        $context = [
            'submitter' => $approval->getUser()->getDisplayName(),
            'approver' => $approver,
            'week' => $week,
            'reason' => $history->getMessage(),
            'status' => $this->translator->trans($status === ApprovalStatus::DENIED ? 'reject' : $status),
            'url' => $url
        ];
        
        $email = (new TemplatedEmail())
            ->to(new Address($approval->getUser()->getEmail()))
            ->subject($this->translator->trans($status === ApprovalStatus::APPROVED ? 'email.subjectApproved' : 'email.subjectReject') . " (" . $week . ")")
            ->htmlTemplate('@Approval/approvedChangeStatus.email.twig')
            ->context($context);
        try {
            $this->kimaiMailer->send($email);

            return true;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }

    public function sendApproveWeekEmail(Approval $approval, ApprovalRepository $approvalRepository): bool
    {
        $submitter = $approval->getUser()->getDisplayName();
        $users = [];
        foreach ($approval->getUser()->getTeams() as $team) {
            $users = array_merge($users, $team->getTeamleads());
        }
        foreach ($users as $user) {
            $approver = $user->getDisplayName();
            $week = $this->formatting->parseDate(clone $approval->getStartDate());
            $context = [
                'submitter' => $submitter,
                'approver' => $approver,
                'week' => $week,
                'url' => $approvalRepository->getUrl((string) $approval->getUser()->getId(), $approval->getStartDate()->format('Y-m-d'))
            ];
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($submitter . " " . $this->translator->trans('email.subjectSubmitted') . " (" . $week . ")")
                ->htmlTemplate('@Approval/approved.email.twig')
                ->context($context);
            try {
                $this->kimaiMailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return false;
            }
        }

        return true;
    }

    public function sendClosedWeekEmail(string $month): bool
    {
        $context = ['monthNames' => $month];
        $users = $this->userRepository->findUsersWithRole('ROLE_SUPER_ADMIN');
        foreach ($users as $user) {
            $email = (new TemplatedEmail())
                ->to(new Address($user->getEmail()))
                ->subject($this->translator->trans('email.closedMonth'))
                ->htmlTemplate('@Approval/closedMonth.email.twig')
                ->context($context);

            try {
                $this->kimaiMailer->send($email);
            } catch (TransportExceptionInterface $e) {
                return false;
            }
        }

        return true;
    }

    public function sendAdminNotSubmittedEmail(array $approvals, array $recipients, OutputInterface $output): bool
    {
        return $this->setApprovalsEmailToAllRecipient(
            $recipients,
            'email.cronjob.adminNotSubmittedSubject',
            '@Approval/cronjob.adminNotSubmitted.email.twig',
            $approvals,
            $output
        );
    }

    public function sendTeamleadNotSubmittedEmail(array $approvals, array $teamLeaders, OutputInterface $output): bool
    {
        return $this->setApprovalsEmailToAllRecipient(
            $teamLeaders,
            'email.cronjob.teamleadNotSubmittedUsersSubject',
            '@Approval/cronjob.teamleadNotSubmittedUsers.email.twig',
            $approvals,
            $output
        );
    }

    public function sendUserNotSubmittedWeeksEmail(array $approvals, User $user, OutputInterface $output): bool
    {
        return $this->setApprovalsEmailToAllRecipient(
            [$user],
            'email.cronjob.userNotSubmittedWeeksSubject',
            '@Approval/cronjob.userNotSubmittedWeeks.email.twig',
            $approvals,
            $output
        );
    }

    private function setApprovalsEmailToAllRecipient(array $recipients, string $str, string $template, array $approvals, OutputInterface $output): bool
    {
        foreach ($recipients as $recipient) {
            $email = (new TemplatedEmail())
                ->to(new Address($recipient->getEmail()))
                ->subject($this->translator->trans($str))
                ->htmlTemplate($template)
                ->context(['context' => $approvals]);

            try {
                $this->kimaiMailer->send($email);
                $output->writeln('<info>' . $recipient->getEmail() . '</info>');
            } catch (TransportExceptionInterface $e) {
                return false;
            }
        }

        return true;
    }
}
