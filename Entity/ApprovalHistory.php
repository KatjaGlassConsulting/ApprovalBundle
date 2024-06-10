<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Entity;

use App\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalHistoryRepository;

#[ORM\Entity(repositoryClass: ApprovalHistoryRepository::class)]
#[ORM\Table(name: 'kimai2_ext_approval_history')]
class ApprovalHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\ManyToOne(targetEntity: Approval::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Approval $approval = null;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;
    #[ORM\ManyToOne(targetEntity: ApprovalStatus::class, inversedBy: 'history')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ApprovalStatus $status = null;
    #[ORM\Column(type: 'datetime')]
    private $date;
    #[ORM\Column(type: 'text', nullable: true)]
    private $message;

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     */
    public function setId($id): void
    {
        $this->id = $id;
    }

    /**
     * @return Approval
     */
    public function getApproval()
    {
        return $this->approval;
    }

    /**
     * @param Approval $approval
     */
    public function setApproval($approval): void
    {
        $this->approval = $approval;
    }

    /**
     * @return User
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser($user): void
    {
        $this->user = $user;
    }

    /**
     * @return ApprovalStatus
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @param ApprovalStatus $status
     */
    public function setStatus($status): void
    {
        $this->status = $status;
    }

    /**
     * @return mixed
     */
    public function getDate()
    {
        return $this->date;
    }

    /**
     * @param mixed $date
     */
    public function setDate($date): void
    {
        $this->date = $date;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @param mixed $message
     */
    public function setMessage($message): void
    {
        $this->message = $message;
    }
}
