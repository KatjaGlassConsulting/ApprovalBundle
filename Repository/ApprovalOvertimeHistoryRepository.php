<?php

namespace KimaiPlugin\ApprovalBundle\Repository;

use KimaiPlugin\ApprovalBundle\Entity\ApprovalOvertimeHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
 
/**
 * @extends ServiceEntityRepository<ApprovalOvertimeHistory>
 *
 * @method ApprovalOvertimeHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApprovalOvertimeHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApprovalOvertimeHistory[]    findAll()
 * @method ApprovalOvertimeHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
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
     * @return int - Returns duration according overtimeHistory for a subject for the selected year until including date
     */
    public function getOvertimeCorrectionForUserByDateInYear(User $user, \DateTime $date): int
    {
        $firstYearDate = clone $date;
        $firstYearDate->setDate($firstYearDate->format('Y'), 1, 1);

        $result = $this->getEntityManager()->createQueryBuilder()
            ->select('sum(duration)')
            ->from(ApprovalOvertimeHistory::class, 'aoh')
            ->andWhere('aoh.user = :user')
            ->andWhere('aoh.applyDate <= :date')
            ->andWhere('aoh.applyDate >= :firstYearDate')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->setParameter('firstYearDate', $firstYearDate)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;

        return ($result !== null) ? $result : 0;
    }
}
