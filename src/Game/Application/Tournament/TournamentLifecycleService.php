<?php

namespace App\Game\Application\Tournament;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use App\Entity\World;
use App\Game\Domain\Economy\EconomyCatalog;
use Doctrine\ORM\EntityManagerInterface;

final class TournamentLifecycleService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
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
        }

        $this->entityManager->flush();

        /** @var list<Tournament> $active */
        $active = $tournamentRepo->findBy(['world' => $world, 'status' => Tournament::STATUS_SCHEDULED], ['id' => 'ASC']);

        foreach ($active as $tournament) {
            $this->registerParticipants($tournament, $worldDay, $charactersById, $goalsByCharacterId);

            $groupDay = $tournament->getAnnounceDay() + 1;
            if ($worldDay === $groupDay) {
                $this->runGroupStage($tournament, $worldDay, $charactersById, $goalsByCharacterId);
            }

            if ($worldDay === $tournament->getResolveDay()) {
                $events = array_merge(
                    $events,
                    $this->resolveKnockout($tournament, $worldDay, $charactersById, $goalsByCharacterId, $economyCatalog),
                );
            }
        }

        return $events;
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
     */
    private function runGroupStage(Tournament $tournament, int $worldDay, array $charactersById, array $goalsByCharacterId): void
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

        if (count($present) < 2) {
            foreach ($present as $c) {
                $cid = (int)$c->getId();
                if (isset($goalsByCharacterId[$cid]) && $goalsByCharacterId[$cid]->getCurrentGoalCode() === 'goal.participate_tournament') {
                    $goalsByCharacterId[$cid]->setCurrentGoalComplete(true);
                }
            }
            return;
        }

        usort($present, function (Character $a, Character $b): int {
            $pa = $this->power($a);
            $pb = $this->power($b);
            if ($pa !== $pb) {
                return $pb <=> $pa;
            }
            return ((int)$a->getId()) <=> ((int)$b->getId());
        });

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
                    $winner                        = $this->pickWinner($tournament, 'group:' . $groupIndex, $matchIndex, $a, $b);
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

                $winner = $this->pickWinner($tournament, $stage, $matchIndex, $a, $b);
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
        $champ  = $this->pickWinner($tournament, 'final', $matchIndex, $finalA, $finalB);
        $runner = $champ === $finalA ? $finalB : $finalA;

        $third = null;
        if (count($semiLosers) === 2) {
            $matchIndex++;
            $third = $this->pickWinner($tournament, 'third_place', $matchIndex, $semiLosers[0], $semiLosers[1]);
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
                'winner_1'      => (int)$champ->getId(),
                'winner_2'      => (int)$runner->getId(),
                'winner_3'      => $third instanceof Character ? (int)$third->getId() : null,
                'prize_1'       => $prize1,
                'prize_2'       => $prize2,
                'prize_3'       => $paid3,
            ],
        );

        return [$event];
    }

    private function power(Character $character): int
    {
        $a = $character->getCoreAttributes();

        return $a->strength + $a->speed + $a->endurance + $a->durability + $a->kiCapacity
            + $a->kiControl + $a->kiRecovery + $a->focus + $a->discipline + $a->adaptability;
    }

    private function pickWinner(Tournament $tournament, string $stage, int $matchIndex, Character $a, Character $b): Character
    {
        $aid = $a->getId();
        $bid = $b->getId();
        if ($aid === null || $bid === null) {
            return $a;
        }

        $pa = $this->power($a);
        $pb = $this->power($b);

        if ($pa === $pb) {
            return (int)$aid <= (int)$bid ? $a : $b;
        }

        $total = max(1, $pa + $pb);
        $roll  = ($this->hashInt(sprintf('t:%d:%s:%d:%d:%d', (int)$tournament->getId(), $stage, $matchIndex, (int)$aid, (int)$bid)) % $total) + 1;

        return $roll <= $pa ? $a : $b;
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

    private function hashInt(string $input): int
    {
        $hash = hash('sha256', $input);

        return (int)hexdec(substr($hash, 0, 8));
    }
}

