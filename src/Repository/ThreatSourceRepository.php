<?php

namespace App\Repository;

use App\Entity\ThreatSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThreatSource>
 *
 * @method ThreatSource|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThreatSource|null findOneBy(array $criteria, array $orderBy = null)
 * @method ThreatSource[]    findAll()
 * @method ThreatSource[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ThreatSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThreatSource::class);
    }
}
