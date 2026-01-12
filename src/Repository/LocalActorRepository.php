<?php

namespace App\Repository;

use App\Entity\LocalActor;
use App\Entity\LocalSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalActor>
 */
class LocalActorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalActor::class);
    }

    public function findPlayerActor(LocalSession $session): ?LocalActor
    {
        return $this->findOneBy(['session' => $session, 'role' => 'player']);
    }

    /**
     * @return list<LocalActor>
     */
    public function findNpcActors(LocalSession $session): array
    {
        /** @var list<LocalActor> $actors */
        $actors = $this->findBy(['session' => $session, 'role' => 'npc'], ['id' => 'ASC']);
        return $actors;
    }
}

