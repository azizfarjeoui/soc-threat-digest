<?php

namespace App\Repository;

use App\Entity\FetchRun;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FetchRun>
 *
 * @method FetchRun|null find($id, $lockMode = null, $lockVersion = null)
 * @method FetchRun|null findOneBy(array $criteria, array $orderBy = null)
 * @method FetchRun[]    findAll()
 * @method FetchRun[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class FetchRunRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FetchRun::class);
    }

    /**
     * Get the latest successful or partial_failure fetch run.
     */
    public function findLatestCompletedRun(): ?FetchRun
    {
        return $this->createQueryBuilder('f')
            ->where('f.status IN (:statuses)')
            ->setParameter('statuses', [FetchRun::STATUS_SUCCESS, FetchRun::STATUS_PARTIAL_FAILURE])
            ->orderBy('f.ranAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
