<?php

namespace App\Repository;

use App\Entity\ThreatReport;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ThreatReport>
 *
 * @method ThreatReport|null find($id, $lockMode = null, $lockVersion = null)
 * @method ThreatReport|null findOneBy(array $criteria, array $orderBy = null)
 * @method ThreatReport[]    findAll()
 * @method ThreatReport[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ThreatReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ThreatReport::class);
    }

    /**
     * Checks if a threat report already exists based on source and externalId.
     */
    public function exists(string $source, string $externalId): bool
    {
        return $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.source = :source')
            ->andWhere('t.externalId = :externalId')
            ->setParameter('source', $source)
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getSingleScalarResult() > 0;
    }

    /**
     * @return array<string, int>
     */
    public function countGroupedBySource(): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.source AS source, COUNT(t.id) AS cnt')
            ->groupBy('t.source')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['source']] = (int) $row['cnt'];
        }

        return $counts;
    }

    public function deleteBySource(string $source): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.source = :source')
            ->setParameter('source', $source)
            ->getQuery()
            ->execute();
    }
}
