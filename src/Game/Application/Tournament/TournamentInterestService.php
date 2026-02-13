<?php

declare(strict_types=1);

namespace App\Game\Application\Tournament;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\Tournament;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Npc\NpcArchetype;
use Doctrine\ORM\EntityManagerInterface;

final class TournamentInterestService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EconomyCatalogProviderInterface $economyCatalogProvider,
    ) {
    }

    /**
     * @param list<Character>          $characters
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param array<int,NpcProfile>    $npcProfilesByCharacterId
     *
     * @return list<CharacterEvent>
     */
    public function advanceDay(
        World $world,
        int $worldDay,
        array $characters,
        array $goalsByCharacterId,
        array $npcProfilesByCharacterId,
    ): array {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        $economyCatalog = $this->economyCatalogProvider->get();

        /** @var list<Tournament> $tournaments */
        $tournaments = $this->entityManager->getRepository(Tournament::class)
            ->findBy(['world' => $world, 'status' => Tournament::STATUS_SCHEDULED], ['id' => 'ASC']);

        if ($tournaments === []) {
            return [];
        }

        $events = [];

        foreach ($characters as $character) {
            $characterId = $character->getId();
            if ($characterId === null) {
                continue;
            }

            $profile = $npcProfilesByCharacterId[(int) $characterId] ?? null;
            if (!$profile instanceof NpcProfile || $profile->getArchetype() !== NpcArchetype::Fighter) {
                continue;
            }

            $goal = $goalsByCharacterId[(int) $characterId] ?? null;
            if ($goal instanceof CharacterGoal && $goal->getCurrentGoalCode() === 'goal.participate_tournament' && !$goal->isCurrentGoalComplete()) {
                continue;
            }

            /** @var array{score:int,distance:int,tournament_id:int,data:array<string,mixed>}|null $bestCommit */
            $bestCommit = null;

            foreach ($tournaments as $tournament) {
                $settlement = $tournament->getSettlement();
                $distance = abs($character->getTileX() - $settlement->getX()) + abs($character->getTileY() - $settlement->getY());
                $registrationCloseDay = $tournament->getAnnounceDay() + 1;

                $reasonCode = null;
                $feasible = true;
                if ($worldDay > $registrationCloseDay) {
                    $feasible = false;
                    $reasonCode = 'registration_closed';
                } elseif (($worldDay + $distance) > $registrationCloseDay) {
                    $feasible = false;
                    $reasonCode = 'cannot_arrive_in_time';
                }

                $factors = $this->computeFactors($character, $tournament, $distance, $economyCatalog);
                $score = array_sum($factors);
                $decision = ($feasible && $score >= $economyCatalog->tournamentInterestCommitThreshold()) ? 'committed' : 'declined';
                if ($reasonCode === null && $decision !== 'committed') {
                    $reasonCode = 'below_threshold';
                }

                $baseData = [
                    'character_id' => (int) $characterId,
                    'tournament_id' => $tournament->getId(),
                    'center_x' => $settlement->getX(),
                    'center_y' => $settlement->getY(),
                    'score_total' => $score,
                    'decision' => $decision,
                    'reason_code' => $reasonCode,
                    'factors' => $factors,
                    'resolve_day' => $tournament->getResolveDay(),
                    'registration_close_day' => $registrationCloseDay,
                ];

                $events[] = new CharacterEvent($world, $character, 'tournament_interest_evaluated', $worldDay, $baseData);

                if ($decision !== 'committed') {
                    continue;
                }

                $candidate = [
                    'score' => $score,
                    'distance' => $distance,
                    'tournament_id' => (int)($tournament->getId() ?? PHP_INT_MAX),
                    'data' => $baseData,
                ];

                if ($bestCommit === null
                    || $candidate['score'] > $bestCommit['score']
                    || ($candidate['score'] === $bestCommit['score'] && $candidate['distance'] < $bestCommit['distance'])
                    || ($candidate['score'] === $bestCommit['score'] && $candidate['distance'] === $bestCommit['distance'] && $candidate['tournament_id'] < $bestCommit['tournament_id'])) {
                    $bestCommit = $candidate;
                }
            }

            if ($bestCommit !== null) {
                /** @var array<string,mixed> $data */
                $data = $bestCommit['data'];
                $events[] = new CharacterEvent($world, $character, 'tournament_interest_committed', $worldDay, $data);
            }
        }

        return $events;
    }

    /** @return array{distance:int,prize_pool:int,archetype_bias:int,money_pressure:int,cooldown_penalty:int} */
    private function computeFactors(Character $character, Tournament $tournament, int $distance, EconomyCatalog $economyCatalog): array
    {
        $distanceWeight = $economyCatalog->tournamentInterestWeightDistance();
        $radius = max(1, $tournament->getRadius());
        $distanceScore = max(0.0, 1 - ($distance / $radius));

        $prizeWeight = $economyCatalog->tournamentInterestWeightPrizePool();
        $prizeScore = min(1.0, $tournament->getPrizePool() / 100);

        $moneyWeight = $economyCatalog->tournamentInterestWeightMoneyPressure();
        $lowThreshold = $character->isEmployed()
            ? $economyCatalog->moneyLowThresholdEmployed()
            : $economyCatalog->moneyLowThresholdUnemployed();
        $moneyScore = $character->getMoney() <= $lowThreshold ? 1.0 : 0.0;

        return [
            'distance' => (int) round($distanceWeight * $distanceScore),
            'prize_pool' => (int) round($prizeWeight * $prizeScore),
            'archetype_bias' => $economyCatalog->tournamentInterestWeightArchetypeBias(),
            'money_pressure' => (int) round($moneyWeight * $moneyScore),
            'cooldown_penalty' => 0,
        ];
    }
}
