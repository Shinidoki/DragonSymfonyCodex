<?php

namespace App\Repository;

use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalIntent>
 */
class LocalIntentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalIntent::class);
    }

    public function findActiveForActor(LocalActor $actor): ?LocalIntent
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.actor = :actor')
            ->setParameter('actor', $actor)
            ->orderBy('i.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

