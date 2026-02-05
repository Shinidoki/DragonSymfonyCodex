<?php

namespace App\Repository;

use App\Entity\CharacterEvent;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterEvent>
 */
class CharacterEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterEvent::class);
    }

    /**
     * @return list<CharacterEvent>
     */
    public function findForResolver(World $world, int $characterId, int $maxDay, int $minIdExclusive): array
    {
        if ($characterId <= 0) {
            throw new \InvalidArgumentException('characterId must be positive.');
        }
        if ($maxDay < 0) {
            throw new \InvalidArgumentException('maxDay must be >= 0.');
        }
        if ($minIdExclusive < 0) {
            throw new \InvalidArgumentException('minIdExclusive must be >= 0.');
        }

        /** @var list<CharacterEvent> $events */
        $events = $this->createQueryBuilder('e')
            ->andWhere('e.world = :world')
            ->andWhere('e.day <= :maxDay')
            ->andWhere('e.id > :minId')
            ->andWhere('(IDENTITY(e.character) = :characterId OR e.character IS NULL)')
            ->andWhere('e.type NOT LIKE :logPrefix')
            ->setParameter('world', $world)
            ->setParameter('characterId', $characterId)
            ->setParameter('maxDay', $maxDay)
            ->setParameter('minId', $minIdExclusive)
            ->setParameter('logPrefix', 'log.%')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }

    /**
     * @return list<CharacterEvent>
     */
    public function findByWorldUpToDay(World $world, int $maxDay): array
    {
        if ($maxDay < 0) {
            throw new \InvalidArgumentException('maxDay must be >= 0.');
        }

        /** @var list<CharacterEvent> $events */
        $events = $this->createQueryBuilder('e')
            ->andWhere('e.world = :world')
            ->andWhere('e.day <= :maxDay')
            ->andWhere('e.type NOT LIKE :logPrefix')
            ->setParameter('world', $world)
            ->setParameter('maxDay', $maxDay)
            ->setParameter('logPrefix', 'log.%')
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $events;
    }
}
