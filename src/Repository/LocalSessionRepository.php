<?php

namespace App\Repository;

use App\Entity\LocalSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalSession>
 */
class LocalSessionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalSession::class);
    }

    public function findActiveForCharacter(int $characterId): ?LocalSession
    {
        return $this->findOneBy(['characterId' => $characterId, 'status' => 'active']);
    }
}

