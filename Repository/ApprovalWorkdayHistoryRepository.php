<?php

namespace KimaiPlugin\ApprovalBundle\Repository;

use KimaiPlugin\ApprovalBundle\Entity\ApprovalWorkdayHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;
 
/**
 * @extends ServiceEntityRepository<ApprovalWorkdayHistory>
 *
 * @method ApprovalWorkdayHistory|null find($id, $lockMode = null, $lockVersion = null)
 * @method ApprovalWorkdayHistory|null findOneBy(array $criteria, array $orderBy = null)
 * @method ApprovalWorkdayHistory[]    findAll()
 * @method ApprovalWorkdayHistory[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ApprovalWorkdayHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ApprovalWorkdayHistory::class);
    }

    public function save(ApprovalWorkdayHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ApprovalWorkdayHistory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

   /**
    * @return ApprovalWorkdayHistory[] Returns an array of ApprovalWorkdayHistory objects
    */
   public function findByUserWorkdayHistory(User $user): array
   {
       return $this->getEntityManager()->createQueryBuilder()
           ->select('aph')
           ->from(ApprovalWorkdayHistory::class, 'aph')
           ->andWhere('aph.user = :user')
           ->setParameter('user', $user)
           ->orderBy('aph.valid_till', 'DESC')
           ->setMaxResults(200)
           ->getQuery()
           ->getResult()
       ;
   }

    /**
     * @return ApprovalWorkdayHistory - Returns null or an ApprovalWorkdayHistory objects
     */
    public function findByUserAndDateWorkdayHistory(User $user, \DateTime $date): ?ApprovalWorkdayHistory
    {
        $day = $date->format('Y-m-d');

        return $this->getEntityManager()->createQueryBuilder()
            ->select('aph')
            ->from(ApprovalWorkdayHistory::class, 'aph')
            ->andWhere('aph.user = :user')
            ->andWhere('aph.validTill >= :date')
            ->setParameter('user', $user)
            ->setParameter('date', $day)
            ->addOrderBy('aph.validTill', 'ASC')
            ->addOrderBy('aph.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    /**
     * @return array
     */
    public function findAll()
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('aph')
            ->from(ApprovalWorkdayHistory::class, 'aph')
            ->addOrderBy('aph.validTill', 'DESC')
            ->addOrderBy('aph.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
