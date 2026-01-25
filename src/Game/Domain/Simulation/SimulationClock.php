<?php

namespace App\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\World;
use App\Game\Domain\Economy\EconomyCatalog;
use App\Game\Domain\Goal\CharacterGoalResolver;
use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalPlanner;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Map\Travel\StepTowardTarget;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;
use App\Game\Domain\Npc\DailyPlanner;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use App\Game\Domain\Training\TrainingContext;
use App\Game\Domain\Transformations\TransformationService;

final class SimulationClock
{
    public function __construct(
        private readonly TrainingGrowthService $trainingGrowth,
        private readonly ?DailyPlanner         $dailyPlanner = null,
        private readonly ?StepTowardTarget     $stepTowardTarget = null,
        private readonly ?TransformationService $transformationService = null,
        private readonly ?CharacterGoalResolver $goalResolver = null,
        private readonly ?GoalPlanner           $goalPlanner = null,
    )
    {
    }

    /**
     * @param list<Character> $characters
     * @param array<int,NpcProfile> $npcProfilesByCharacterId
     * @param list<TileCoord>       $dojoTiles
     * @param list<TileCoord>  $settlementTiles
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param list<CharacterEvent>     $events
     * @param list<Settlement> $settlements
     *
     * @return list<CharacterEvent>
     */
    public function advanceDays(
        World             $world,
        array             $characters,
        int               $days,
        TrainingIntensity $intensity,
        array             $npcProfilesByCharacterId = [],
        array             $dojoTiles = [],
        array           $settlementTiles = [],
        array        $goalsByCharacterId = [],
        array        $events = [],
        ?GoalCatalog $goalCatalog = null,
        array           $settlements = [],
        ?EconomyCatalog $economyCatalog = null,
    ): array
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $emitted                     = [];

        $planner = $this->dailyPlanner ?? new DailyPlanner();
        $stepper = $this->stepTowardTarget ?? new StepTowardTarget();
        $transformations = $this->transformationService ?? new TransformationService();
        $goalResolver = $this->goalResolver ?? new CharacterGoalResolver();

        $dojoIndex = $this->buildDojoIndex($dojoTiles);
        $settlementsByCoord = $this->buildSettlementIndex($settlements);

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

            $workLedger = [];

            foreach ($characters as $character) {
                $character->advanceDays(1);

                $profile = null;
                if ($character->getId() !== null) {
                    $profile = $npcProfilesByCharacterId[(int)$character->getId()] ?? null;
                }

                $plan = null;

                $goal = null;
                if ($goalCatalog instanceof GoalCatalog && $character->getId() !== null) {
                    $goal = $goalsByCharacterId[(int)$character->getId()] ?? null;
                }

                if ($goal instanceof CharacterGoal && $goalCatalog instanceof GoalCatalog) {
                    $goalResolver->resolveForDay($character, $goal, $goalCatalog, $world->getCurrentDay(), $events);
                    $this->applyWorkFocusTarget($character, $goal, $goalCatalog);

                    if ($this->goalPlanner instanceof GoalPlanner) {
                        $currentGoalCode = $goal->getCurrentGoalCode();
                        if ($currentGoalCode !== null && !$goal->isCurrentGoalComplete()) {
                            $result = $this->goalPlanner->step(
                                character: $character,
                                world: $world,
                                currentGoalCode: $currentGoalCode,
                                data: $goal->getCurrentGoalData() ?? [],
                                context: new GoalContext($dojoTiles, $settlementTiles, $settlementsByCoord, $economyCatalog),
                                catalog: $goalCatalog,
                            );

                            $goal->setCurrentGoalData($result->data);
                            $goal->setCurrentGoalComplete($result->completed);
                            $plan = $result->plan;

                            if (
                                $economyCatalog instanceof EconomyCatalog
                                && $currentGoalCode === 'goal.find_job'
                                && $result->completed
                                && !$character->isEmployed()
                            ) {
                                $tx = $result->data['target_x'] ?? null;
                                $ty = $result->data['target_y'] ?? null;

                                if (is_int($tx) && is_int($ty) && $tx >= 0 && $ty >= 0) {
                                    $archetype = $profile instanceof NpcProfile ? $profile->getArchetype()->value : 'civilian';
                                    $jobCode   = $economyCatalog->pickJobForArchetypeRandom($archetype);
                                    if ($jobCode === null) {
                                        $jobCode = array_key_first($economyCatalog->jobs());
                                    }

                                    if (is_string($jobCode) && trim($jobCode) !== '') {
                                        $character->setEmployment($jobCode, $tx, $ty);
                                    }
                                }
                            }

                            $emitted = array_merge($emitted, $result->events);
                        }
                    }
                }

                if ($plan === null) {
                    $plan = $planner->planFor($character, $profile, $dojoTiles);
                }

                $workFraction = $this->workFraction($character, $plan, $economyCatalog);
                if ($economyCatalog instanceof EconomyCatalog && $workFraction > 0.0 && $character->isEmployed()) {
                    $sx      = (int)$character->getEmploymentSettlementX();
                    $sy      = (int)$character->getEmploymentSettlementY();
                    $key     = sprintf('%d:%d', $sx, $sy);
                    $jobCode = (string)$character->getEmploymentJobCode();

                    $workLedger[$key][] = [
                        'character'  => $character,
                        'work_units' => $economyCatalog->jobWageWeight($jobCode) * $workFraction,
                    ];
                }

                if ($plan->activity === DailyActivity::Train) {
                    $multiplier = isset($dojoIndex[sprintf('%d:%d', $character->getTileX(), $character->getTileY())])
                        ? TrainingContext::Dojo->multiplier()
                        : TrainingContext::Wilderness->multiplier();

                    $trainFraction = max(0.0, 1.0 - $workFraction);
                    if ($trainFraction > 0.0) {
                        $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier * $trainFraction);
                        $character->applyCoreAttributes($after);
                    }
                }

                if ($plan->activity === DailyActivity::Travel) {
                    if (!$character->hasTravelTarget() && $plan->travelTarget instanceof TileCoord) {
                        $character->setTravelTarget($plan->travelTarget->x, $plan->travelTarget->y);

                        if ($profile instanceof NpcProfile && $profile->getArchetype() === NpcArchetype::Wanderer) {
                            $profile->incrementWanderSequence();
                        }
                    }

                    if ($character->hasTravelTarget()) {
                        $current = new TileCoord($character->getTileX(), $character->getTileY());
                        $target  = new TileCoord((int)$character->getTargetTileX(), (int)$character->getTargetTileY());
                        $next    = $stepper->step($current, $target);
                        $character->setTilePosition($next->x, $next->y);

                        if ($next->x === $target->x && $next->y === $target->y) {
                            $character->clearTravelTarget();
                        }
                    }
                }

                $this->advanceTransformationDay($character, $transformations);
            }

            $this->applyEconomyDay(
                worldDay: $world->getCurrentDay(),
                economyCatalog: $economyCatalog,
                settlementsByCoord: $settlementsByCoord,
                ledgerBySettlement: $workLedger,
            );

            if ($economyCatalog instanceof EconomyCatalog && $goalCatalog instanceof GoalCatalog) {
                $emitted = array_merge(
                    $emitted,
                    $this->emitMoneyLowEvents($world, $characters, $economyCatalog, $goalsByCharacterId),
                );
            }
        }

        return $emitted;
    }

    /**
     * For MVP: suspendable long actions from local mode.
     * - Player character does NOT travel; they either train with multiplier or rest.
     * - Other characters follow the normal daily planner (train/travel).
     *
     * @param list<Character> $characters
     * @param array<int,NpcProfile> $npcProfilesByCharacterId
     * @param list<TileCoord>       $dojoTiles
     * @param list<TileCoord>  $settlementTiles
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param list<CharacterEvent>     $events
     * @param list<Settlement> $settlements
     *
     * @return list<CharacterEvent>
     */
    public function advanceDaysForLongAction(
        World             $world,
        array             $characters,
        int               $days,
        TrainingIntensity $intensity,
        int               $playerCharacterId,
        ?float            $trainingMultiplier,
        array $npcProfilesByCharacterId = [],
        array $dojoTiles = [],
        array           $settlementTiles = [],
        array        $goalsByCharacterId = [],
        array        $events = [],
        ?GoalCatalog $goalCatalog = null,
        array           $settlements = [],
        ?EconomyCatalog $economyCatalog = null,
    ): array
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }
        if ($playerCharacterId <= 0) {
            throw new \InvalidArgumentException('playerCharacterId must be positive.');
        }
        if ($trainingMultiplier !== null && $trainingMultiplier <= 0) {
            throw new \InvalidArgumentException('trainingMultiplier must be > 0 when provided.');
        }

        $emitted                     = [];

        $planner = $this->dailyPlanner ?? new DailyPlanner();
        $stepper = $this->stepTowardTarget ?? new StepTowardTarget();
        $transformations = $this->transformationService ?? new TransformationService();
        $goalResolver = $this->goalResolver ?? new CharacterGoalResolver();

        $dojoIndex = $this->buildDojoIndex($dojoTiles);
        $settlementsByCoord = $this->buildSettlementIndex($settlements);

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

            $workLedger = [];

            foreach ($characters as $character) {
                $character->advanceDays(1);

                if ((int)$character->getId() === $playerCharacterId) {
                    if ($trainingMultiplier !== null) {
                        $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $trainingMultiplier);
                        $character->applyCoreAttributes($after);
                    }

                    $this->advanceTransformationDay($character, $transformations);
                    continue;
                }

                $profile = null;
                if ($character->getId() !== null) {
                    $profile = $npcProfilesByCharacterId[(int)$character->getId()] ?? null;
                }

                $plan = null;

                $goal = null;
                if ($goalCatalog instanceof GoalCatalog && $character->getId() !== null) {
                    $goal = $goalsByCharacterId[(int)$character->getId()] ?? null;
                }

                if ($goal instanceof CharacterGoal && $goalCatalog instanceof GoalCatalog) {
                    $goalResolver->resolveForDay($character, $goal, $goalCatalog, $world->getCurrentDay(), $events);
                    $this->applyWorkFocusTarget($character, $goal, $goalCatalog);

                    if ($this->goalPlanner instanceof GoalPlanner) {
                        $currentGoalCode = $goal->getCurrentGoalCode();
                        if ($currentGoalCode !== null && !$goal->isCurrentGoalComplete()) {
                            $result = $this->goalPlanner->step(
                                character: $character,
                                world: $world,
                                currentGoalCode: $currentGoalCode,
                                data: $goal->getCurrentGoalData() ?? [],
                                context: new GoalContext($dojoTiles, $settlementTiles, $settlementsByCoord, $economyCatalog),
                                catalog: $goalCatalog,
                            );

                            $goal->setCurrentGoalData($result->data);
                            $goal->setCurrentGoalComplete($result->completed);
                            $plan = $result->plan;

                            if (
                                $economyCatalog instanceof EconomyCatalog
                                && $currentGoalCode === 'goal.find_job'
                                && $result->completed
                                && !$character->isEmployed()
                            ) {
                                $tx = $result->data['target_x'] ?? null;
                                $ty = $result->data['target_y'] ?? null;

                                if (is_int($tx) && is_int($ty) && $tx >= 0 && $ty >= 0) {
                                    $archetype = $profile instanceof NpcProfile ? $profile->getArchetype()->value : 'civilian';
                                    $jobCode   = $economyCatalog->pickJobForArchetypeRandom($archetype);
                                    if ($jobCode === null) {
                                        $jobCode = array_key_first($economyCatalog->jobs());
                                    }

                                    if (is_string($jobCode) && trim($jobCode) !== '') {
                                        $character->setEmployment($jobCode, $tx, $ty);
                                    }
                                }
                            }

                            $emitted = array_merge($emitted, $result->events);
                        }
                    }
                }

                if ($plan === null) {
                    $plan = $planner->planFor($character, $profile, $dojoTiles);
                }

                $workFraction = $this->workFraction($character, $plan, $economyCatalog);
                if ($economyCatalog instanceof EconomyCatalog && $workFraction > 0.0 && $character->isEmployed()) {
                    $sx      = (int)$character->getEmploymentSettlementX();
                    $sy      = (int)$character->getEmploymentSettlementY();
                    $key     = sprintf('%d:%d', $sx, $sy);
                    $jobCode = (string)$character->getEmploymentJobCode();

                    $workLedger[$key][] = [
                        'character'  => $character,
                        'work_units' => $economyCatalog->jobWageWeight($jobCode) * $workFraction,
                    ];
                }

                if ($plan->activity === DailyActivity::Train) {
                    $multiplier = isset($dojoIndex[sprintf('%d:%d', $character->getTileX(), $character->getTileY())])
                        ? TrainingContext::Dojo->multiplier()
                        : TrainingContext::Wilderness->multiplier();

                    $trainFraction = max(0.0, 1.0 - $workFraction);
                    if ($trainFraction > 0.0) {
                        $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier * $trainFraction);
                        $character->applyCoreAttributes($after);
                    }
                }

                if ($plan->activity === DailyActivity::Travel) {
                    if (!$character->hasTravelTarget() && $plan->travelTarget instanceof TileCoord) {
                        $character->setTravelTarget($plan->travelTarget->x, $plan->travelTarget->y);

                        if ($profile instanceof NpcProfile && $profile->getArchetype() === NpcArchetype::Wanderer) {
                            $profile->incrementWanderSequence();
                        }
                    }

                    if ($character->hasTravelTarget()) {
                        $current = new TileCoord($character->getTileX(), $character->getTileY());
                        $target  = new TileCoord((int)$character->getTargetTileX(), (int)$character->getTargetTileY());
                        $next    = $stepper->step($current, $target);
                        $character->setTilePosition($next->x, $next->y);

                        if ($next->x === $target->x && $next->y === $target->y) {
                            $character->clearTravelTarget();
                        }
                    }
                }

                $this->advanceTransformationDay($character, $transformations);
            }

            $this->applyEconomyDay(
                worldDay: $world->getCurrentDay(),
                economyCatalog: $economyCatalog,
                settlementsByCoord: $settlementsByCoord,
                ledgerBySettlement: $workLedger,
            );

            if ($economyCatalog instanceof EconomyCatalog && $goalCatalog instanceof GoalCatalog) {
                $emitted = array_merge(
                    $emitted,
                    $this->emitMoneyLowEvents($world, $characters, $economyCatalog, $goalsByCharacterId),
                );
            }
        }

        return $emitted;
    }

    private function applyWorkFocusTarget(Character $character, CharacterGoal $goal, GoalCatalog $catalog): void
    {
        $currentGoalCode = $goal->getCurrentGoalCode();
        if ($currentGoalCode === null) {
            return;
        }

        $target = $catalog->currentGoalWorkFocusTarget($currentGoalCode);
        if ($target === null) {
            return;
        }

        $character->setWorkFocus($target);
    }

    private function workFraction(Character $character, DailyPlan $plan, ?EconomyCatalog $economyCatalog): float
    {
        if (!$economyCatalog instanceof EconomyCatalog) {
            return 0.0;
        }
        if (!$character->isEmployed()) {
            return 0.0;
        }
        if ($plan->activity === DailyActivity::Travel) {
            return 0.0;
        }

        $jobCode = $character->getEmploymentJobCode();
        if (!is_string($jobCode) || trim($jobCode) === '') {
            return 0.0;
        }

        $sx = $character->getEmploymentSettlementX();
        $sy = $character->getEmploymentSettlementY();
        if (!is_int($sx) || !is_int($sy) || $sx < 0 || $sy < 0) {
            return 0.0;
        }

        $radius = $economyCatalog->jobWorkRadius($jobCode);
        $dist   = abs($character->getTileX() - $sx) + abs($character->getTileY() - $sy);
        if ($dist > $radius) {
            return 0.0;
        }

        return max(0.0, min(1.0, $character->getWorkFocus() / 100));
    }

    /**
     * @param array<string,Settlement>                                        $settlementsByCoord
     * @param array<string,list<array{character:Character,work_units:float}>> $ledgerBySettlement
     */
    private function applyEconomyDay(int $worldDay, ?EconomyCatalog $economyCatalog, array $settlementsByCoord, array $ledgerBySettlement): void
    {
        if (!$economyCatalog instanceof EconomyCatalog) {
            return;
        }

        foreach ($ledgerBySettlement as $coordKey => $entries) {
            $settlement = $settlementsByCoord[$coordKey] ?? null;
            if (!$settlement instanceof Settlement) {
                continue;
            }
            if ($settlement->getLastSimDayApplied() === $worldDay) {
                continue;
            }

            $sumWorkUnits = 0.0;
            foreach ($entries as $entry) {
                $sumWorkUnits += $entry['work_units'];
            }
            if ($sumWorkUnits <= 0.0) {
                $settlement->setLastSimDayApplied($worldDay);
                continue;
            }

            $perWorkUnit = $economyCatalog->settlementPerWorkUnitBase()
                + ($settlement->getProsperity() * $economyCatalog->settlementPerWorkUnitProsperityMult());

            $gross = $perWorkUnit * $sumWorkUnits;

            $r = $economyCatalog->settlementRandomnessPct();
            if ($r > 0.0) {
                $roll  = random_int(-1_000_000, 1_000_000) / 1_000_000;
                $gross *= (1.0 + ($roll * $r));
            }

            $grossInt = max(0, (int)floor($gross));

            $wagePoolRate = $economyCatalog->settlementWagePoolRate();
            $taxRate      = $economyCatalog->settlementTaxRate();

            $wagePool = (int)floor($grossInt * $wagePoolRate);
            $retained = $grossInt - $wagePool;

            $paidWages = 0;
            $taxTotal  = 0;

            foreach ($entries as $entry) {
                $share     = $entry['work_units'] / $sumWorkUnits;
                $grossWage = (int)floor($wagePool * $share);
                if ($grossWage <= 0) {
                    continue;
                }

                $paidWages += $grossWage;

                $tax = (int)floor($grossWage * $taxRate);
                if ($tax < 0) {
                    $tax = 0;
                }
                $net = $grossWage - $tax;
                if ($net < 0) {
                    $net = 0;
                }

                $taxTotal += $tax;
                $entry['character']->addMoney($net);
            }

            $leftover = $wagePool - $paidWages;
            if ($leftover < 0) {
                $leftover = 0;
            }

            $settlement->addToTreasury($retained + $taxTotal + $leftover);
            $settlement->setLastSimDayApplied($worldDay);
        }
    }

    /**
     * Emit low-money events to allow the goal system to react on the next day.
     *
     * @param list<Character>          $characters
     * @param array<int,CharacterGoal> $goalsByCharacterId
     *
     * @return list<CharacterEvent>
     */
    private function emitMoneyLowEvents(World $world, array $characters, EconomyCatalog $economyCatalog, array $goalsByCharacterId): array
    {
        $emitted = [];
        $day     = $world->getCurrentDay();

        foreach ($characters as $character) {
            $id = $character->getId();
            if ($id === null) {
                continue;
            }

            $goal = $goalsByCharacterId[(int)$id] ?? null;
            if (!$goal instanceof CharacterGoal) {
                continue;
            }

            $currentGoal = $goal->getCurrentGoalCode();

            if ($character->isEmployed()) {
                $threshold = $economyCatalog->moneyLowThresholdEmployed();
                if ($threshold > 0 && $character->getMoney() < $threshold && $currentGoal !== 'goal.earn_money') {
                    $emitted[] = new CharacterEvent($world, $character, 'money_low_employed', $day);
                }
                continue;
            }

            $threshold = $economyCatalog->moneyLowThresholdUnemployed();
            if ($threshold > 0 && $character->getMoney() < $threshold && $currentGoal !== 'goal.find_job') {
                $emitted[] = new CharacterEvent($world, $character, 'money_low_unemployed', $day);
            }
        }

        return $emitted;
    }

    /**
     * @param list<Settlement> $settlements
     *
     * @return array<string,Settlement>
     */
    private function buildSettlementIndex(array $settlements): array
    {
        $byCoord = [];
        foreach ($settlements as $settlement) {
            $byCoord[sprintf('%d:%d', $settlement->getX(), $settlement->getY())] = $settlement;
        }

        return $byCoord;
    }

    private function advanceTransformationDay(Character $character, TransformationService $service): void
    {
        $state = $character->getTransformationState();
        if ($state->active !== null) {
            $state = $service->deactivate($state);
        }

        $character->setTransformationState($service->advanceDay($state));
    }

    /**
     * @param list<TileCoord> $dojoTiles
     *
     * @return array<string,true>
     */
    private function buildDojoIndex(array $dojoTiles): array
    {
        $index = [];
        foreach ($dojoTiles as $tile) {
            $index[sprintf('%d:%d', $tile->x, $tile->y)] = true;
        }

        return $index;
    }
}
