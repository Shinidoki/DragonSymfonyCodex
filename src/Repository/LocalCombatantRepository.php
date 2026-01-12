<?php

namespace App\Repository;

use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalCombatant>
 */
class LocalCombatantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalCombatant::class);
    }

    public function findOneByCombatAndActorId(LocalCombat $combat, int $actorId): ?LocalCombatant
    {
        return $this->findOneBy(['combat' => $combat, 'actorId' => $actorId]);
    }

    /**
     * @return list<LocalCombatant>
     */
    public function findByCombat(LocalCombat $combat): array
    {
        /** @var list<LocalCombatant> $combatants */
        $combatants = $this->findBy(['combat' => $combat], ['id' => 'ASC']);
        return $combatants;
    }
}

