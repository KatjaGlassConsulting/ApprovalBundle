<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalStatus;

/**
 * @method ApprovalStatus|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApprovalStatus|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApprovalStatus[] findAll()
 * @method ApprovalStatus[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApprovalStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApprovalStatus::class);
    }
}
