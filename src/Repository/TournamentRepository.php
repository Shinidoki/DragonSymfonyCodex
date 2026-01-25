<?php

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tournament>
 */
class TournamentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tournament::class);
    }

    public function findOneByRequestEventId(int $requestEventId): ?Tournament
    {
        if ($requestEventId <= 0) {
            throw new \InvalidArgumentException('requestEventId must be positive.');
        }

        $t = $this->findOneBy(['requestEventId' => $requestEventId]);
        if (!$t instanceof Tournament) {
            return null;
        }

        return $t;
    }

    /**
     * @return list<Tournament>
     */
    public function findScheduledToResolveUpToDay(World $world, int $day): array
    {
        if ($day < 0) {
            throw new \InvalidArgumentException('day must be >= 0.');
        }

        /** @var list<Tournament> $items */
        $items = $this->createQueryBuilder('t')
            ->andWhere('t.world = :world')
            ->andWhere('t.status = :status')
            ->andWhere('t.resolveDay <= :day')
            ->setParameter('world', $world)
            ->setParameter('status', Tournament::STATUS_SCHEDULED)
            ->setParameter('day', $day)
            ->orderBy('t.resolveDay', 'ASC')
            ->addOrderBy('t.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $items;
    }
}

