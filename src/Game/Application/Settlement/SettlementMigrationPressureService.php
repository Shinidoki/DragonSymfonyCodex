<?php

declare(strict_types=1);

namespace App\Game\Application\Settlement;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Application\Economy\EconomyCatalogProviderInterface;
use App\Game\Domain\Goal\GoalCatalog;
use Doctrine\ORM\EntityManagerInterface;

final class SettlementMigrationPressureService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EconomyCatalogProviderInterface $economyCatalogProvider,
    ) {
    }

    /**
     * @param list<Character>  $characters
     * @param list<Settlement> $settlements
     *
     * @return list<CharacterEvent>
     */
    /**
     * @param array<int,CharacterGoal> $goalsByCharacterId
     */
    public function advanceDay(World $world, int $worldDay, array $characters, array $settlements, array $goalsByCharacterId = [], ?GoalCatalog $goalCatalog = null): array
    {
        if ($worldDay < 0) {
            throw new \InvalidArgumentException('worldDay must be >= 0.');
        }

        $catalog = $this->economyCatalogProvider->get();
        $dailyCap = $catalog->migrationPressureDailyMoveCap();
        $threshold = $catalog->migrationPressureCommitThreshold();
        $maxDistance = $catalog->migrationPressureMaxTravelDistance();
        $cooldownDays = $catalog->migrationPressureMoveCooldownDays();
        $lookbackDays = $catalog->migrationPressureLookbackDays();

        $latestMigrationDayByCharacterId = $this->latestMigrationDayByCharacterId($world, $worldDay, $lookbackDays, $characters);

        $settlementsByKey = [];
        foreach ($settlements as $settlement) {
            $settlementsByKey[$this->xyKey($settlement->getX(), $settlement->getY())] = $settlement;
        }

        if ($settlementsByKey === []) {
            return [];
        }

        $populationByKey = [];
        foreach ($characters as $character) {
            $key = $this->xyKey($character->getTileX(), $character->getTileY());
            $populationByKey[$key] = ($populationByKey[$key] ?? 0) + 1;
        }

        $candidates = [];

        foreach ($characters as $character) {
            $characterId = $character->getId();
            if ($characterId === null) {
                continue;
            }

            if ($character->isEmployed()) {
                continue;
            }

            if ($this->hasNonInterruptibleCurrentGoal($character, $goalsByCharacterId, $goalCatalog)) {
                continue;
            }

            if ($this->isInCooldownWindow($worldDay, $character, $cooldownDays, $lookbackDays, $latestMigrationDayByCharacterId)) {
                continue;
            }

            $sourceKey = $this->xyKey($character->getTileX(), $character->getTileY());
            $source = $settlementsByKey[$sourceKey] ?? null;
            if (!$source instanceof Settlement) {
                continue;
            }

            $best = null;
            foreach ($settlements as $destination) {
                if ($destination === $source) {
                    continue;
                }

                $distance = abs($source->getX() - $destination->getX()) + abs($source->getY() - $destination->getY());
                if ($distance > $maxDistance) {
                    continue;
                }

                $scoreComponents = [
                    'prosperity_gap' => (int) round(max(0, $destination->getProsperity() - $source->getProsperity()) * ($catalog->migrationPressureWeightProsperityGap() / 100)),
                    'treasury_gap' => (int) round(max(0, $destination->getTreasury() - $source->getTreasury()) * ($catalog->migrationPressureWeightTreasuryGap() / 1000)),
                    'crowding_gap' => (int) round(max(0, ($populationByKey[$sourceKey] ?? 0) - ($populationByKey[$this->xyKey($destination->getX(), $destination->getY())] ?? 0)) * $catalog->migrationPressureWeightCrowdingGap()),
                ];

                $scoreTotal = array_sum($scoreComponents);
                if ($scoreTotal < $threshold) {
                    continue;
                }

                if ($best === null || $scoreTotal > $best['score']) {
                    $best = [
                        'destination' => $destination,
                        'score' => $scoreTotal,
                        'components' => $scoreComponents,
                    ];
                }
            }

            if ($best === null) {
                continue;
            }

            $candidates[] = [
                'character' => $character,
                'character_id' => (int) $characterId,
                'source' => $source,
                'destination' => $best['destination'],
                'score' => $best['score'],
                'components' => $best['components'],
            ];
        }

        usort($candidates, static function (array $left, array $right): int {
            $scoreComparison = $right['score'] <=> $left['score'];
            if ($scoreComparison !== 0) {
                return $scoreComparison;
            }

            return $left['character_id'] <=> $right['character_id'];
        });

        $events = [];
        foreach (array_slice($candidates, 0, $dailyCap) as $candidate) {
            /** @var Settlement $source */
            $source = $candidate['source'];
            /** @var Settlement $target */
            $target = $candidate['destination'];

            /** @var Character $candidateCharacter */
            $candidateCharacter = $candidate['character'];

            $events[] = new CharacterEvent(
                world: $world,
                character: $candidateCharacter,
                type: 'settlement_migration_committed',
                day: $worldDay,
                data: [
                    'character_id' => $candidate['character_id'],
                    'from_x' => $source->getX(),
                    'from_y' => $source->getY(),
                    'target_x' => $target->getX(),
                    'target_y' => $target->getY(),
                    'score_total' => $candidate['score'],
                    'score_components' => $candidate['components'],
                    'world_day' => $worldDay,
                ],
            );
        }

        return $events;
    }

    /**
     * @param array<int,CharacterGoal> $goalsByCharacterId
     */
    private function hasNonInterruptibleCurrentGoal(Character $character, array $goalsByCharacterId, ?GoalCatalog $goalCatalog): bool
    {
        if (!$goalCatalog instanceof GoalCatalog) {
            return false;
        }

        $characterId = $character->getId();
        if (!is_int($characterId)) {
            return false;
        }

        $goal = $goalsByCharacterId[$characterId] ?? null;
        if (!$goal instanceof CharacterGoal) {
            return false;
        }

        $currentGoalCode = $goal->getCurrentGoalCode();
        if (!is_string($currentGoalCode) || $currentGoalCode === '' || $goal->isCurrentGoalComplete()) {
            return false;
        }

        return !$goalCatalog->currentGoalInterruptible($currentGoalCode);
    }

    /** @param array<int,int> $latestMigrationDayByCharacterId */
    private function isInCooldownWindow(int $worldDay, Character $character, int $cooldownDays, int $lookbackDays, array $latestMigrationDayByCharacterId): bool
    {
        if ($cooldownDays <= 0) {
            return false;
        }

        $characterId = $character->getId();
        if (!is_int($characterId)) {
            return false;
        }

        $lastDay = $latestMigrationDayByCharacterId[$characterId] ?? null;
        if (!is_int($lastDay)) {
            return false;
        }

        $age = $worldDay - $lastDay;

        return $age <= $cooldownDays && $age <= $lookbackDays;
    }

    /**
     * @param list<Character> $characters
     *
     * @return array<int,int>
     */
    private function latestMigrationDayByCharacterId(World $world, int $worldDay, int $lookbackDays, array $characters): array
    {
        $charactersWithIds = array_values(array_filter(
            $characters,
            static fn (Character $character): bool => is_int($character->getId()),
        ));

        if ($charactersWithIds === []) {
            return [];
        }

        $lookbackStartDay = max(0, $worldDay - max(0, $lookbackDays));

        /** @var list<CharacterEvent> $recent */
        $recent = $this->entityManager->getRepository(CharacterEvent::class)
            ->createQueryBuilder('e')
            ->andWhere('e.world = :world')
            ->andWhere('e.character IN (:characters)')
            ->andWhere('e.type = :type')
            ->andWhere('e.day >= :lookbackStartDay')
            ->setParameter('world', $world)
            ->setParameter('characters', $charactersWithIds)
            ->setParameter('type', 'settlement_migration_committed')
            ->setParameter('lookbackStartDay', $lookbackStartDay)
            ->orderBy('e.day', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->getQuery()
            ->getResult();

        if (!is_iterable($recent)) {
            return [];
        }

        $latestByCharacterId = [];
        foreach ($recent as $event) {
            $characterId = $event->getCharacter()?->getId();
            $day = $event->getDay();
            if (!is_int($characterId) || !is_int($day)) {
                continue;
            }

            if (!array_key_exists($characterId, $latestByCharacterId)) {
                $latestByCharacterId[$characterId] = $day;
            }
        }

        return $latestByCharacterId;
    }

    private function xyKey(int $x, int $y): string
    {
        return $x . ':' . $y;
    }
}
