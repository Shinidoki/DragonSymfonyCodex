<?php

namespace App\Repository;

use App\Entity\CharacterGoal;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterGoal>
 */
class CharacterGoalRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterGoal::class);
    }

    /**
     * @return list<CharacterGoal>
     */
    public function findByWorld(World $world): array
    {
        /** @var list<CharacterGoal> $goals */
        $goals = $this->createQueryBuilder('g')
            ->join('g.character', 'c')
            ->andWhere('c.world = :world')
            ->setParameter('world', $world)
            ->getQuery()
            ->getResult();

        return $goals;
    }
}

