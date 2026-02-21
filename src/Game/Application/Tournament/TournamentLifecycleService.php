<?php

namespace App\Game\Application\Tournament;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use App\Entity\World;
use App\Game\Domain\Combat\SimulatedCombat\CombatRules;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatant;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatResolver;
use App\Game\Domain\Economy\EconomyCatalog;
use Doctrine\ORM\EntityManagerInterface;

final class TournamentLifecycleService
{
    public function __construct(
        private readonly EntityManagerInterface   $entityManager,
        private readonly ?SimulatedCombatResolver $combatResolver = null,
    )
    {
    }

    /**
     * @param list<Character>          $characters
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param list<CharacterEvent>     $emittedEvents
     * @param list<Settlement>         $settlements
     *
     * @return list<CharacterEvent> events emitted by tournament lifecycle (persisted by caller)
     */
    public function advanceDay(
        World           $world,
        int             $worldDay,
        array           $characters,
        array           $goalsByCharacterId,
        array           $emittedEvents,
        array           $settlements,
        ?EconomyCatalog $economyCatalog,
    ): array
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        if (!$economyCatalog instanceof EconomyCatalog || $settlements === []) {
            return [];
        }

        $events = [];

        $settlementsByCoord = [];
        foreach ($settlements as $s) {
            $settlementsByCoord[sprintf('%d:%d', $s->getX(), $s->getY())] = $s;
        }

        $charactersById = [];
        foreach ($characters as $c) {
            $id = $c->getId();
            if ($id !== null) {
                $charactersById[(int)$id] = $c;
            }
        }

        $tournamentRepo = $this->entityManager->getRepository(Tournament::class);

        $scheduledBySettlementKey = [];
        /** @var list<Tournament> $alreadyScheduled */
        $alreadyScheduled = $tournamentRepo->findBy(['world' => $world, 'status' => Tournament::STATUS_SCHEDULED], ['id' => 'ASC']);
        foreach ($alreadyScheduled as $t) {
            $s                              = $t->getSettlement();
            $sid                            = $s->getId();
            $key                            = $sid !== null ? sprintf('id:%d', (int)$sid) : sprintf('coord:%d:%d', $s->getX(), $s->getY());
            $scheduledBySettlementKey[$key] = true;
        }

        foreach ($emittedEvents as $event) {
            if ($event->getType() !== 'tournament_announced') {
                continue;
            }
            $eventId = $event->getId();
            if ($eventId === null) {
                continue;
            }

            $existing = $tournamentRepo->findOneBy(['requestEventId' => $eventId]);
            if ($existing instanceof Tournament) {
                continue;
            }

            $data = $event->getData();
            if (!is_array($data)) {
                continue;
            }

            $x = $data['center_x'] ?? null;
            $y = $data['center_y'] ?? null;
            if (!is_int($x) || !is_int($y) || $x < 0 || $y < 0) {
                continue;
            }

            $settlement = $settlementsByCoord[sprintf('%d:%d', $x, $y)] ?? null;
            if (!$settlement instanceof Settlement) {
                continue;
            }

            $settlementId  = $settlement->getId();
            $settlementKey = $settlementId !== null
                ? sprintf('id:%d', (int)$settlementId)
                : sprintf('coord:%d:%d', $settlement->getX(), $settlement->getY());
            if (isset($scheduledBySettlementKey[$settlementKey])) {
                continue;
            }

            $announceDay = $event->getDay();
            $resolveDay  = $data['resolve_day'] ?? null;
            if (!is_int($resolveDay) || $resolveDay < $announceDay) {
                $resolveDay = $announceDay + max(1, $economyCatalog->tournamentDurationDays());
            }

            $spend     = $data['spend'] ?? 0;
            $prizePool = $data['prize_pool'] ?? 0;
            $radius    = $data['radius'] ?? 0;

            if (!is_int($spend) || $spend < 0) {
                $spend = 0;
            }
            if (!is_int($prizePool) || $prizePool < 0) {
                $prizePool = 0;
            }
            if (!is_int($radius) || $radius < 0) {
                $radius = 0;
            }
            if ($prizePool > $spend) {
                $prizePool = $spend;
            }

            $tournament = new Tournament(
                world: $world,
                settlement: $settlement,
                announceDay: $announceDay,
                resolveDay: $resolveDay,
                spend: $spend,
                prizePool: $prizePool,
                radius: $radius,
                requestEventId: $eventId,
            );
            $this->entityManager->persist($tournament);

            $scheduledBySettlementKey[$settlementKey] = true;
        }

        $this->entityManager->flush();

        /** @var list<Tournament> $active */
        $active = $tournamentRepo->findBy(['world' => $world, 'status' => Tournament::STATUS_SCHEDULED], ['id' => 'ASC']);

        foreach ($active as $tournament) {
            $this->registerParticipants($tournament, $worldDay, $charactersById, $goalsByCharacterId);

            $groupDay = $tournament->getAnnounceDay() + 1;
            if ($worldDay === $groupDay) {
                $events = array_merge(
                    $events,
                    $this->runGroupStage($tournament, $worldDay, $charactersById, $goalsByCharacterId),
                );
            }

            if ($tournament->getStatus() === Tournament::STATUS_SCHEDULED && $worldDay === $tournament->getResolveDay()) {
                $events = array_merge(
                    $events,
                    $this->resolveKnockout($tournament, $worldDay, $charactersById, $goalsByCharacterId, $economyCatalog),
                );
            }
        }

        return $events;
    }

    /**
     * @param list<Character>          $characters
     * @param array<int,CharacterGoal> $goalsByCharacterId
     */
    public function registerParticipantsForDay(World $world, int $worldDay, array $characters, array $goalsByCharacterId): void
    {
        $charactersById = [];
        foreach ($characters as $c) {
            $id = $c->getId();
            if ($id !== null) {
                $charactersById[(int) $id] = $c;
            }
        }

        /** @var list<Tournament> $active */
        $active = $this->entityManager->getRepository(Tournament::class)
            ->findBy(['world' => $world, 'status' => Tournament::STATUS_SCHEDULED], ['id' => 'ASC']);

        foreach ($active as $tournament) {
            $this->registerParticipants($tournament, $worldDay, $charactersById, $goalsByCharacterId);
        }
    }

    /**
     * @param array<int,Character>     $charactersById
     * @param array<int,CharacterGoal> $goalsByCharacterId
     */
    private function registerParticipants(Tournament $tournament, int $worldDay, array $charactersById, array $goalsByCharacterId): void
    {
        $registrationCloseDay = $tournament->getAnnounceDay() + 1;
        if ($worldDay > $registrationCloseDay) {
            return;
        }

        $participantRepo = $this->entityManager->getRepository(TournamentParticipant::class);

        $sx = $tournament->getSettlement()->getX();
        $sy = $tournament->getSettlement()->getY();

        foreach ($goalsByCharacterId as $characterId => $goal) {
            if ($goal->getCurrentGoalCode() !== 'goal.participate_tournament') {
                continue;
            }
            if ($goal->isCurrentGoalComplete()) {
                continue;
            }

            $character = $charactersById[$characterId] ?? null;
            if (!$character instanceof Character) {
                continue;
            }

            $data = $goal->getCurrentGoalData() ?? [];
            $cx   = $data['center_x'] ?? null;
            $cy   = $data['center_y'] ?? null;
            if (!is_int($cx) || !is_int($cy) || $cx !== $sx || $cy !== $sy) {
                continue;
            }

            if ($character->getTileX() !== $sx || $character->getTileY() !== $sy) {
                continue;
            }

            $existing = $participantRepo->findOneBy(['tournament' => $tournament, 'character' => $character]);
            if ($existing instanceof TournamentParticipant) {
                continue;
            }

            $p = new TournamentParticipant($tournament, $character, $worldDay);
            $this->entityManager->persist($p);
        }
    }

    /**
     * @param array<int,Character>     $charactersById
     * @param array<int,CharacterGoal> $goalsByCharacterId
     *
     * @return list<CharacterEvent>
     */
    private function runGroupStage(Tournament $tournament, int $worldDay, array $charactersById, array $goalsByCharacterId): array
    {
        $participantRepo = $this->entityManager->getRepository(TournamentParticipant::class);
        /** @var list<TournamentParticipant> $participants */
        $participants = $participantRepo->findBy(['tournament' => $tournament], ['id' => 'ASC']);

        $sx = $tournament->getSettlement()->getX();
        $sy = $tournament->getSettlement()->getY();

        $present = [];
        foreach ($participants as $p) {
            $cid = $p->getCharacter()->getId();
            if ($cid === null) {
                continue;
            }
            $c = $charactersById[(int)$cid] ?? null;
            if (!$c instanceof Character) {
                continue;
            }
            if ($c->getTileX() !== $sx || $c->getTileY() !== $sy) {
                continue;
            }
            $present[] = $c;
        }

        if (count($present) < 4) {
            return $this->cancelTournamentInsufficientParticipants($tournament, $worldDay, $participants, $goalsByCharacterId);
        }

        usort($present, function (Character $a, Character $b): int {
            $pa = $this->power($a);
            $pb = $this->power($b);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return ((int)$a->getId()) <=> ((int)$b->getId());
        });

        [$techniquesByCharacterId, $transformationsByCharacterId] = $this->loadCombatKnowledge($present);
        $fightEvents        = [];

        $groupSize = 4;
        $groups    = array_chunk($present, $groupSize);

        $groupRanks = [];
        $matchIndex = 1;

        foreach ($groups as $groupIndex => $group) {
            $points = [];
            foreach ($group as $c) {
                $points[(int)$c->getId()] = 0;
            }

            $n = count($group);
            for ($i = 0; $i < $n; $i++) {
                for ($j = $i + 1; $j < $n; $j++) {
                    $a                             = $group[$i];
                    $b                             = $group[$j];
                    $winner = $this->pickWinner($tournament, $worldDay, 'group:' . $groupIndex, $matchIndex, $a, $b, $techniquesByCharacterId, $transformationsByCharacterId, $fightEvents);
                    $points[(int)$winner->getId()] += 3;
                    $matchIndex++;
                }
            }

            usort($group, function (Character $a, Character $b) use ($points): int {
                $pa = $points[(int)$a->getId()] ?? 0;
                $pb = $points[(int)$b->getId()] ?? 0;
                if ($pa !== $pb) {
                    return $pb <=> $pa;
                }
                $ra = $this->power($a);
                $rb = $this->power($b);
                if ($ra !== $rb) {
                    return $rb <=> $ra;
                }
                return ((int)$a->getId()) <=> ((int)$b->getId());
            });

            $groupRanks[] = $group;
        }

        $countPresent = count($present);
        $bracketSize  = $this->powerOfTwoFloor($countPresent);
        if ($bracketSize < 2) {
            $bracketSize = 2;
        }

        $qualifiers = [];
        for ($rank = 0; count($qualifiers) < $bracketSize; $rank++) {
            $any = false;
            foreach ($groupRanks as $group) {
                if (isset($group[$rank])) {
                    $qualifiers[] = $group[$rank];
                    $any          = true;
                }
                if (count($qualifiers) >= $bracketSize) {
                    break;
                }
            }
            if (!$any) {
                break;
            }
        }

        $qualifierIds = [];
        foreach ($qualifiers as $i => $c) {
            $qualifierIds[(int)$c->getId()] = $i + 1;
        }

        foreach ($participants as $p) {
            $cid = $p->getCharacter()->getId();
            if ($cid === null) {
                continue;
            }

            $seed = $qualifierIds[(int)$cid] ?? null;
            if ($seed !== null) {
                $p->setSeed($seed);
                continue;
            }

            if (in_array($cid, array_map(static fn(Character $c): int => (int)$c->getId(), $present), true)) {
                $p->markEliminated($worldDay);
                $p->setFinalRank(null);

                if (isset($goalsByCharacterId[(int)$cid]) && $goalsByCharacterId[(int)$cid]->getCurrentGoalCode() === 'goal.participate_tournament') {
                    $goalsByCharacterId[(int)$cid]->setCurrentGoalComplete(true);
                }
            }
        }

        return $fightEvents;
    }

    /**
     * @param list<TournamentParticipant> $participants
     * @param array<int,CharacterGoal>    $goalsByCharacterId
     *
     * @return list<CharacterEvent>
     */
    private function cancelTournamentInsufficientParticipants(
        Tournament $tournament,
        int        $worldDay,
        array      $participants,
        array      $goalsByCharacterId,
    ): array
    {
        $tournament->getSettlement()->addToTreasury($tournament->getPrizePool());

        foreach ($participants as $p) {
            $cid = $p->getCharacter()->getId();
            if ($cid === null) {
                continue;
            }

            $p->markEliminated($worldDay);
            $p->setFinalRank(null);

            if (isset($goalsByCharacterId[(int)$cid]) && $goalsByCharacterId[(int)$cid]->getCurrentGoalCode() === 'goal.participate_tournament') {
                $goalsByCharacterId[(int)$cid]->setCurrentGoalComplete(true);
            }
        }

        $tournament->markCanceled();

        $event = new CharacterEvent(
            world: $tournament->getWorld(),
            character: null,
            type: 'tournament_canceled',
            day: $worldDay,
            data: [
                'tournament_id' => $tournament->getId(),
                'announce_day'  => $tournament->getAnnounceDay(),
                'center_x'      => $tournament->getSettlement()->getX(),
                'center_y'      => $tournament->getSettlement()->getY(),
                'outcome'       => 'canceled',
                'participant_count' => 0,
                'registered_count'  => count($participants),
                'reason'        => 'insufficient_participants',
                'participants'  => count($participants),
            ],
        );

        return [$event];
    }

    /**
     * @param array<int,Character>     $charactersById
     * @param array<int,CharacterGoal> $goalsByCharacterId
     *
     * @return list<CharacterEvent>
     */
    private function resolveKnockout(
        Tournament     $tournament,
        int            $worldDay,
        array          $charactersById,
        array          $goalsByCharacterId,
        EconomyCatalog $economyCatalog,
    ): array
    {
        if ($tournament->getStatus() !== Tournament::STATUS_SCHEDULED) {
            return [];
        }

        $participantRepo = $this->entityManager->getRepository(TournamentParticipant::class);
        /** @var list<TournamentParticipant> $participants */
        $participants = $participantRepo->findBy(['tournament' => $tournament], ['seed' => 'ASC', 'id' => 'ASC']);

        $seeded = [];
        foreach ($participants as $p) {
            if ($p->getSeed() === null) {
                continue;
            }
            $cid = $p->getCharacter()->getId();
            if ($cid === null) {
                continue;
            }
            $c = $charactersById[(int)$cid] ?? null;
            if ($c instanceof Character) {
                $seeded[] = $c;
            }
        }

        if (count($seeded) < 2) {
            $tournament->markResolved();
            return [];
        }

        [$techniquesByCharacterId, $transformationsByCharacterId] = $this->loadCombatKnowledge($seeded);
        $fightEvents = [];

        usort($seeded, fn(Character $a, Character $b): int => ((int)$a->getId()) <=> ((int)$b->getId()));

        // Re-order by seed to create the initial bracket order.
        $bySeed = [];
        foreach ($participants as $p) {
            $seed = $p->getSeed();
            $cid  = $p->getCharacter()->getId();
            if ($seed === null || $cid === null) {
                continue;
            }
            $c = $charactersById[(int)$cid] ?? null;
            if ($c instanceof Character) {
                $bySeed[$seed] = $c;
            }
        }
        ksort($bySeed);
        $round = array_values($bySeed);

        $semiLosers = [];
        $matchIndex = 1;
        $stage      = 'knockout';

        while (count($round) > 2) {
            $next = [];
            $n    = count($round);

            for ($i = 0; $i < (int)floor($n / 2); $i++) {
                $a = $round[$i];
                $b = $round[$n - 1 - $i];

                $winner = $this->pickWinner($tournament, $worldDay, $stage, $matchIndex, $a, $b, $techniquesByCharacterId, $transformationsByCharacterId, $fightEvents);
                $loser  = $winner === $a ? $b : $a;

                if ($n === 4) {
                    $semiLosers[] = $loser;
                }

                $next[] = $winner;
                $matchIndex++;
            }

            $round = $next;
        }

        $finalA = $round[0];
        $finalB = $round[1];
        $champ       = $this->pickWinner($tournament, $worldDay, 'final', $matchIndex, $finalA, $finalB, $techniquesByCharacterId, $transformationsByCharacterId, $fightEvents);
        $runner = $champ === $finalA ? $finalB : $finalA;

        $third = null;
        if (count($semiLosers) === 2) {
            $matchIndex++;
            $third = $this->pickWinner($tournament, $worldDay, 'third_place', $matchIndex, $semiLosers[0], $semiLosers[1], $techniquesByCharacterId, $transformationsByCharacterId, $fightEvents);
        }

        $prizePool = $tournament->getPrizePool();
        $prize1    = (int)floor($prizePool * 0.5);
        $prize2    = (int)floor($prizePool * 0.3);
        $prize3    = $prizePool - $prize1 - $prize2;

        $champ->addMoney($prize1);
        $runner->addMoney($prize2);

        $paid3 = 0;
        if ($third instanceof Character) {
            $third->addMoney($prize3);
            $paid3 = $prize3;
        } else {
            // If no third place exists, return that portion to the settlement treasury.
            $tournament->getSettlement()->addToTreasury($prize3);
        }

        $participantCount = 0;
        foreach ($participants as $p) {
            $participantCount++;
        }
        $tournament->getSettlement()->addFame($economyCatalog->tournamentPerParticipantFame() * $participantCount);

        foreach ($participants as $p) {
            $cid = $p->getCharacter()->getId();
            if ($cid === null) {
                continue;
            }
            $c = $charactersById[(int)$cid] ?? null;
            if (!$c instanceof Character) {
                continue;
            }

            $rank = null;
            if ((int)$c->getId() === (int)$champ->getId()) {
                $rank = 1;
            } elseif ((int)$c->getId() === (int)$runner->getId()) {
                $rank = 2;
            } elseif ($third instanceof Character && (int)$c->getId() === (int)$third->getId()) {
                $rank = 3;
            }

            if (is_int($rank)) {
                $p->markWinner($rank);
            } else {
                $p->markEliminated($worldDay);
            }

            if (isset($goalsByCharacterId[(int)$cid]) && $goalsByCharacterId[(int)$cid]->getCurrentGoalCode() === 'goal.participate_tournament') {
                $goalsByCharacterId[(int)$cid]->setCurrentGoalComplete(true);
            }
        }

        $tournament->markResolved();

        $event = new CharacterEvent(
            world: $tournament->getWorld(),
            character: null,
            type: 'tournament_resolved',
            day: $worldDay,
            data: [
                'tournament_id' => $tournament->getId(),
                'center_x'      => $tournament->getSettlement()->getX(),
                'center_y'      => $tournament->getSettlement()->getY(),
                'outcome'       => 'resolved',
                'participant_count' => $participantCount,
                'registered_count'  => $participantCount,
                'winner_1'      => (int)$champ->getId(),
                'winner_2'      => (int)$runner->getId(),
                'winner_3'      => $third instanceof Character ? (int)$third->getId() : null,
                'prize_1'       => $prize1,
                'prize_2'       => $prize2,
                'prize_3'       => $paid3,
            ],
        );

        return array_merge([$event], $fightEvents);
    }

    /**
     * @param array<int,list<CharacterTechnique>>      $techniquesByCharacterId
     * @param array<int,list<CharacterTransformation>> $transformationsByCharacterId
     * @param list<CharacterEvent> $fightEvents
     */
    private function pickWinner(
        Tournament $tournament,
        int   $worldDay,
        string     $stage,
        int        $matchIndex,
        Character  $a,
        Character  $b,
        array      $techniquesByCharacterId,
        array      $transformationsByCharacterId,
        array &$fightEvents,
    ): Character
    {
        $aid = $a->getId();
        $bid = $b->getId();
        if ($aid === null || $bid === null) {
            return $a;
        }

        // Ensure both participants have stable in-memory technique/transformation knowledge for this fight.
        $aTech  = $techniquesByCharacterId[(int)$aid] ?? [];
        $bTech  = $techniquesByCharacterId[(int)$bid] ?? [];
        $aTrans = $transformationsByCharacterId[(int)$aid] ?? [];
        $bTrans = $transformationsByCharacterId[(int)$bid] ?? [];

        $resolver = $this->combatResolver ?? new SimulatedCombatResolver();

        $result = $resolver->resolve(
            combatants: [
                new SimulatedCombatant($a, teamId: (int)$aid, techniques: $aTech, transformations: $aTrans),
                new SimulatedCombatant($b, teamId: (int)$bid, techniques: $bTech, transformations: $bTrans),
            ],
            rules: new CombatRules(allowFriendlyFire: true),
        );

        $fightEvents[] = new CharacterEvent(
            world: $tournament->getWorld(),
            character: null,
            type: 'sim_fight_resolved',
            day: $worldDay,
            data: [
                'context'       => 'tournament',
                'tournament_id' => $tournament->getId(),
                'center_x'      => $tournament->getSettlement()->getX(),
                'center_y'      => $tournament->getSettlement()->getY(),
                'stage'         => $stage,
                'match_index'   => $matchIndex,
                'a_id'          => (int)$aid,
                'b_id'          => (int)$bid,
                'winner_id'     => $result->winnerCharacterId,
                'actions'       => $result->actions,
            ],
        );

        return $result->winnerCharacterId === (int)$aid ? $a : $b;
    }

    private function power(Character $character): int
    {
        $a = $character->getCoreAttributes();

        return $a->strength + $a->speed + $a->endurance + $a->durability + $a->kiCapacity
            + $a->kiControl + $a->kiRecovery + $a->focus + $a->discipline + $a->adaptability;
    }

    /**
     * @param list<Character> $characters
     *
     * @return array{0:array<int,list<CharacterTechnique>>,1:array<int,list<CharacterTransformation>>}
     */
    private function loadCombatKnowledge(array $characters): array
    {
        if ($characters === []) {
            return [[], []];
        }

        /** @var list<CharacterTechnique> $techniques */
        $techniques = $this->entityManager->getRepository(CharacterTechnique::class)->findBy(['character' => $characters], ['id' => 'ASC']);
        /** @var list<CharacterTransformation> $transformations */
        $transformations = $this->entityManager->getRepository(CharacterTransformation::class)->findBy(['character' => $characters], ['id' => 'ASC']);

        $techniquesByCharacterId = [];
        foreach ($techniques as $k) {
            $cid = $k->getCharacter()->getId();
            if ($cid !== null) {
                $techniquesByCharacterId[(int)$cid][] = $k;
            }
        }

        $transformationsByCharacterId = [];
        foreach ($transformations as $k) {
            $cid = $k->getCharacter()->getId();
            if ($cid !== null) {
                $transformationsByCharacterId[(int)$cid][] = $k;
            }
        }

        return [$techniquesByCharacterId, $transformationsByCharacterId];
    }

    private function powerOfTwoFloor(int $n): int
    {
        if ($n <= 0) {
            return 0;
        }

        $p = 1;
        while (($p * 2) <= $n) {
            $p *= 2;
        }

        return $p;
    }
}
