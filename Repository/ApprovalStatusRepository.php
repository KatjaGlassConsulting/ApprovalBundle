<?php

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
