<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use KimaiPlugin\ApprovalBundle\Repository\ApprovalStatusRepository;

#[ORM\Entity(repositoryClass: ApprovalStatusRepository::class)]
#[ORM\Table(name: 'kimai2_ext_approval_status')]
class ApprovalStatus
{
    public const SUBMITTED = 'submitted';
    public const GRANTED = 'granted'; //WRONG - only for migration
    public const DENIED = 'denied';
    public const APPROVED = 'approved';
    public const NOT_SUBMITTED = 'not_submitted';

    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    private $id;
    #[ORM\Column(type: 'string', length: 255)]
    private $name;
    #[ORM\OneToMany(mappedBy: 'status', targetEntity: ApprovalHistory::class)]
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
     * @return mixed
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param mixed $name
     */
    public function setName($name): void
    {
        $this->name = $name;
    }

    /**
     * @return mixed
     */
    public function getHistory()
    {
        return $this->history;
    }

    /**
     * @param mixed $history
     */
    public function setHistory($history): void
    {
        $this->history = $history;
    }
}
