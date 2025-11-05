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
use KimaiPlugin\ApprovalBundle\Entity\ApprovalHistory;

/**
 * @method ApprovalHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApprovalHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApprovalHistory[] findAll()
 * @method ApprovalHistory[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApprovalHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApprovalHistory::class);
    }

    public function persistFlush(ApprovalHistory $approveHistory)
    {
        $this->getEntityManager()->persist($approveHistory);
        $this->getEntityManager()->flush();
    }

    public function flush()
    {
        $this->getEntityManager()->flush();
    }

    public function findLastStatus($approvalId): ?ApprovalHistory
    {
        $query = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('ah')
            ->from(ApprovalHistory::class, 'ah')
            ->join('ah.approval', 'a')
            ->where('a.id = :id')
            ->setParameter('id', $approvalId)
            ->orderBy('ah.date', 'DESC')
            ->addOrderBy('ah.id', 'DESC')
            ->setMaxResults(1);

        return $query->getQuery()->getOneOrNullResult();
    }
}
