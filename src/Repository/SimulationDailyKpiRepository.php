<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SimulationDailyKpi;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SimulationDailyKpi>
 */
class SimulationDailyKpiRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SimulationDailyKpi::class);
    }

    /** @return list<SimulationDailyKpi> */
    public function findByWorldDayRange(World $world, int $fromDay, int $toDay, int $limit = 2000): array
    {
        if ($fromDay < 0 || $toDay < $fromDay) {
            throw new \InvalidArgumentException('Invalid day range.');
        }
        if ($limit < 1) {
            throw new \InvalidArgumentException('limit must be >= 1.');
        }

        /** @var list<SimulationDailyKpi> $rows */
        $rows = $this->createQueryBuilder('k')
            ->andWhere('k.world = :world')
            ->andWhere('k.day >= :fromDay')
            ->andWhere('k.day <= :toDay')
            ->setParameter('world', $world)
            ->setParameter('fromDay', $fromDay)
            ->setParameter('toDay', $toDay)
            ->orderBy('k.day', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $rows;
    }
}
