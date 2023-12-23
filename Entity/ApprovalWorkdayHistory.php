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
use KimaiPlugin\ApprovalBundle\Repository\ApprovalWorkdayHistoryRepository;

#[ORM\Entity(repositoryClass: ApprovalWorkdayHistoryRepository::class)]
#[ORM\Table(name: 'kimai2_ext_approval_workday_history')]
class ApprovalWorkdayHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: 'integer')]
    private ?int $monday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $tuesday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $wednesday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $thursday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $friday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $saturday = null;

    #[ORM\Column(type: 'integer')]
    private ?int $sunday = null;

    #[ORM\Column(type: 'date')]
    private $validTill = null;

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

    public function getMonday(): ?int
    {
        return $this->monday;
    }

    public function setMonday(int $monday): self
    {
        $this->monday = $monday;

        return $this;
    }

    public function getTuesday(): ?int
    {
        return $this->tuesday;
    }

    public function setTuesday(int $tuesday): self
    {
        $this->tuesday = $tuesday;

        return $this;
    }

    public function getWednesday(): ?int
    {
        return $this->wednesday;
    }

    public function setWednesday(int $wednesday): self
    {
        $this->wednesday = $wednesday;

        return $this;
    }

    public function getThursday(): ?int
    {
        return $this->thursday;
    }

    public function setThursday(int $thursday): self
    {
        $this->thursday = $thursday;

        return $this;
    }

    public function getFriday(): ?int
    {
        return $this->friday;
    }

    public function setFriday(int $friday): self
    {
        $this->friday = $friday;

        return $this;
    }

    public function getSaturday(): ?int
    {
        return $this->saturday;
    }

    public function setSaturday(int $saturday): self
    {
        $this->saturday = $saturday;

        return $this;
    }

    public function getSunday(): ?int
    {
        return $this->sunday;
    }

    public function setSunday(int $sunday): self
    {
        $this->sunday = $sunday;

        return $this;
    }

    public function getValidTill(): ?\DateTimeInterface
    {
        return $this->validTill;
    }

    public function setValidTill(\DateTimeInterface $validTill): self
    {
        $this->validTill = $validTill;

        return $this;
    }
}
