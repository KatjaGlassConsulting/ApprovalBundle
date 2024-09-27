<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace KimaiPlugin\ApprovalBundle\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use KimaiPlugin\ApprovalBundle\Entity\ApprovalOvertimeHistory;

/**
 * @extends ServiceEntityRepository<ApprovalOvertimeHistory>
 *
 * @method ApprovalOvertimeHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApprovalOvertimeHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApprovalOvertimeHistory[] findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApprovalOvertimeHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApprovalOvertimeHistory::class);
    }

    public function save(ApprovalOvertimeHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApprovalOvertimeHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * @return ApprovalOvertimeHistory[]
     */
    public function findAll()
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('aoh')
            ->from(ApprovalOvertimeHistory::class, 'aoh')
            ->addOrderBy('aoh.user')
            ->addOrderBy('aoh.applyDate', 'DESC')
            ->addOrderBy('aoh.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int - Returns duration according overtimeHistory for a subject for the selected year until including date
     */
    public function getOvertimeCorrectionForUserByStardEndDate(User $user, \DateTime $startDate, \DateTime $endDate): int
    {
        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('sum(aoh.duration)')
            ->from(ApprovalOvertimeHistory::class, 'aoh')
            ->andWhere('aoh.user = :user')
            ->andWhere('aoh.applyDate <= :endDate')
            ->andWhere('aoh.applyDate >= :startDate')
            ->setParameter('user', $user)
            ->setParameter('endDate', $endDate)
            ->setParameter('startDate', $startDate)
            ->setMaxResults(1)
            ->getQuery()
            ->getSingleScalarResult()
        ;

        if ($result === null) {
            return 0;
        }

        return (int) $result;
    }
}
