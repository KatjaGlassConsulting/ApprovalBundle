<?php

namespace KimaiPlugin\ApprovalBundle\Entity;

use App\Repository\ApprovalWorkdayHistoryRepository;
use App\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\Entity(repositoryClass=ApprovalWorkdayHistoryRepository::class)
 * @ORM\Table(name="kimai2_ext_approval_workday_history")
 */
class ApprovalWorkdayHistory
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private ?int $id = null;

    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     */
    private ?User $user = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $monday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $tuesday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $wednesday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $thursday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $friday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $saturday = null;

    /**
     * @ORM\Column(type="DateInterval")
     */
    private ?\DateInterval $sunday = null;

    /**
     * @ORM\Column(type="date")
     */
    private $validTill = null;

    public function __construct()
    {
        $this->workdayHistory = new ArrayCollection();
    }

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

    public function getMonday(): ?\DateInterval
    {
        return $this->monday;
    }

    public function setMonday(\DateInterval $monday): self
    {
        $this->monday = $monday;

        return $this;
    }

    public function getTuesday(): ?\DateInterval
    {
        return $this->tuesday;
    }

    public function setTuesday(\DateInterval $tuesday): self
    {
        $this->tuesday = $tuesday;

        return $this;
    }

    public function getWednesday(): ?\DateInterval
    {
        return $this->wednesday;
    }

    public function setWednesday(\DateInterval $wednesday): self
    {
        $this->wednesday = $wednesday;

        return $this;
    }

    public function getThursday(): ?\DateInterval
    {
        return $this->thursday;
    }

    public function setThursday(\DateInterval $thursday): self
    {
        $this->thursday = $thursday;

        return $this;
    }

    public function getFriday(): ?\DateInterval
    {
        return $this->friday;
    }

    public function setFriday(\DateInterval $friday): self
    {
        $this->friday = $friday;

        return $this;
    }

    public function getSaturday(): ?\DateInterval
    {
        return $this->saturday;
    }

    public function setSaturday(\DateInterval $saturday): self
    {
        $this->saturday = $saturday;

        return $this;
    }

    public function getSunday(): ?\DateInterval
    {
        return $this->sunday;
    }

    public function setSunday(\DateInterval $sunday): self
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
