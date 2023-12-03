<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Entity;

use App\Entity\User;
use App\Repository\ApprovalOvertimeHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ApprovalOvertimeHistoryRepository::class)]
#[ORM\Table(name: 'kimai2_ext_approval_overtime_history')]
class ApprovalOvertimeHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * @var User
     */
    #[ORM\ManyToOne(targetEntity: 'App\Entity\User')]
    #[ORM\JoinColumn(onDelete: 'CASCADE', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private ?int $duration = null;

    #[ORM\Column(type: 'date')]
    private $applyDate;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUserId(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;

        return $this;
    }

    public function getApplyDate(): \DateTimeInterface
    {
        return $this->applyDate;
    }

    public function setApplyDate(\DateTimeInterface $applyDate): self
    {
        $this->applyDate = $applyDate;

        return $this;
    }
}
