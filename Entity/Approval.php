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
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;

/**
 * @ORM\Entity(repositoryClass=ApprovalRepository::class)
 * @ORM\Table(name="kimai2_ext_approval")
 */
class Approval
{
    /**
     * @ORM\Id
     * @ORM\GeneratedValue
     * @ORM\Column(type="integer")
     */
    private $id;
    /**
     * @var User
     * @ORM\ManyToOne(targetEntity="App\Entity\User")
     * @ORM\JoinColumn(onDelete="CASCADE", nullable=false)
     */
    private $user;
    /**
     * @ORM\Column(type="date")
     */
    private $startDate;
    /**
     * @ORM\Column(type="date")
     */
    private $endDate;
    /**
     * @ORM\Column(type="integer")
     */
    private $expectedDuration;
    /**
     * @ORM\Column(type="integer")
     */
    private $actualDuration;
    /**
     * @ORM\Column(type="datetime")
     */
    private $creationDate;
    /**
     * @ORM\OneToMany(targetEntity="KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory", mappedBy="approval")
     */
    private $history;

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
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @param User $user
     */
    public function setUser(User $user): void
    {
        $this->user = $user;
    }

    /**
     * @return mixed
     */
    public function getStartDate()
    {
        return $this->startDate;
    }

    /**
     * @param mixed $startDate
     */
    public function setStartDate($startDate): void
    {
        $this->startDate = $startDate;
    }

    /**
     * @return mixed
     */
    public function getEndDate()
    {
        return $this->endDate;
    }

    /**
     * @param mixed $endDate
     */
    public function setEndDate($endDate): void
    {
        $this->endDate = $endDate;
    }

    /**
     * @return mixed
     */
    public function getExpectedDuration()
    {
        return $this->expectedDuration;
    }

    /**
     * @param mixed $expectedDuration
     */
    public function setExpectedDuration($expectedDuration): void
    {
        $this->expectedDuration = $expectedDuration;
    }

    /**
     * @return mixed
     */
    public function getActualDuration()
    {
        return $this->actualDuration;
    }

    /**
     * @param mixed $expectedDuration
     */
    public function setActualDuration($actualDuration): void
    {
        $this->actualDuration = $actualDuration;
    }

    /**
     * @return mixed
     */
    public function getCreationDate()
    {
        return $this->creationDate;
    }

    /**
     * @param mixed $creationDate
     */
    public function setCreationDate($creationDate): void
    {
        $this->creationDate = $creationDate;
    }

    /**
     * @return mixed
     */
    public function getHistory()
    {
        $history = $this->history;

        return \gettype($history) === 'object' ? $history->toArray() : $history;
    }

    /**
     * @param mixed $history
     */
    public function setHistory($history): void
    {
        $this->history = $history;
    }
}
