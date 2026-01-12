<?php

namespace App\Repository;

use App\Entity\LocalCombat;
use App\Entity\LocalSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalCombat>
 */
class LocalCombatRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalCombat::class);
    }

    public function findOneForSession(LocalSession $session): ?LocalCombat
    {
        return $this->findOneBy(['session' => $session]);
    }
}

