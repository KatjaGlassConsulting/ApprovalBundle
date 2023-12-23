<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Entity;

use App\Entity\User;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalRepository;

#[ORM\Entity(repositoryClass: ApprovalRepository::class)]
#[ORM\Table(name: 'kimai2_ext_approval')]
class Approval
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;
    #[ORM\Column(type: 'date')]
    private $startDate;
    #[ORM\Column(type: 'date')]
    private $endDate;
    #[ORM\Column(type: 'integer')]
    private $expectedDuration;
    #[ORM\Column(type: 'integer')]
    private int $actualDuration = 0;
    #[ORM\Column(type: 'datetime')]
    private $creationDate;
    #[ORM\OneToMany(mappedBy: 'approval', targetEntity: ApprovalHistory::class)]
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

    public function getActualDuration(): int
    {
        return $this->actualDuration;
    }

    public function setActualDuration(int $actualDuration): void
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
     * @return array<ApprovalHistory>
     */
    public function getHistory(): array
    {
        if ($this->history instanceof Collection) {
            return $this->history->toArray();
        }

        return $this->history ?? [];
    }

    public function hasHistory(): bool
    {
        return \count($this->getHistory()) > 0;
    }

    /**
     * @param array<ApprovalHistory>|null $history
     */
    public function setHistory(?array $history): void
    {
        $this->history = $history;
    }
}
