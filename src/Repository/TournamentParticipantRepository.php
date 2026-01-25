<?php

namespace App\Repository;

use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TournamentParticipant>
 */
class TournamentParticipantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TournamentParticipant::class);
    }

    public function findOneByTournamentAndCharacter(Tournament $tournament, int $characterId): ?TournamentParticipant
    {
        if ($characterId <= 0) {
            throw new \InvalidArgumentException('characterId must be positive.');
        }

        $p = $this->findOneBy(['tournament' => $tournament, 'character' => $characterId]);
        if (!$p instanceof TournamentParticipant) {
            return null;
        }

        return $p;
    }

    /**
     * @return list<TournamentParticipant>
     */
    public function findByTournament(Tournament $tournament): array
    {
        /** @var list<TournamentParticipant> $items */
        $items = $this->findBy(['tournament' => $tournament], ['id' => 'ASC']);

        return $items;
    }
}

