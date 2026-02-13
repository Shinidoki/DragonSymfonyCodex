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
        array $settlementBuildingsByCoord = [],
        array $activeSettlementProjectsByCoord = [],
        array $dojoTrainingMultipliersByCoord = [],
        array $dojoMasterCharacterIdByCoord = [],
        array $dojoTrainingFeesByCoord = [],
        array $settlementTournamentFeedbackByCoord = [],
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
        $charactersById = $this->buildCharacterIndex($characters);

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
                $day = $world->getCurrentDay();

                $dailyLogData                            = [
                    'activity'      => null,
                    'work_fraction' => 0.0,
                    'job_code'      => $character->getEmploymentJobCode(),
                    'employment_x'  => $character->getEmploymentSettlementX(),
                    'employment_y'  => $character->getEmploymentSettlementY(),
                    'archetype'     => $profile instanceof NpcProfile ? $profile->getArchetype()->value : null,
                ];

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
                                context: new GoalContext(
                                    dojoTiles: $dojoTiles,
                                    settlementTiles: $settlementTiles,
                                    settlementsByCoord: $settlementsByCoord,
                                    settlementBuildingsByCoord: $settlementBuildingsByCoord,
                                    activeSettlementProjectsByCoord: $activeSettlementProjectsByCoord,
                                    economyCatalog: $economyCatalog,
                                    settlementTournamentFeedbackByCoord: $settlementTournamentFeedbackByCoord,
                                    events: $events,
                                ),
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
                $dailyLogData['activity']                = $plan->activity->value;
                $dailyLogData['work_fraction']           = $workFraction;
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
                    $coordKey  = sprintf('%d:%d', $character->getTileX(), $character->getTileY());
                    $levelMult = $dojoTrainingMultipliersByCoord[$coordKey] ?? null;
                    $masterId = $dojoMasterCharacterIdByCoord[$coordKey] ?? null;

                    if ((is_float($levelMult) || is_int($levelMult)) && is_int($masterId) && $masterId > 0) {
                        $multiplier = (float)$levelMult;
                    } else {
                        $multiplier = is_int($masterId) && $masterId > 0
                            ? TrainingContext::Dojo->multiplier()
                            : TrainingContext::Wilderness->multiplier();
                    }

                    $trainFraction = max(0.0, 1.0 - $workFraction);
                    $dailyLogData['train_fraction']      = $trainFraction;
                    $dailyLogData['training_multiplier'] = $multiplier;
                    $dailyLogData['training_context']    = is_int($masterId) && $masterId > 0 ? 'dojo' : 'wilderness';
                    if ($trainFraction > 0.0) {
                        if ($economyCatalog instanceof EconomyCatalog && is_int($masterId) && $masterId > 0) {
                            $fee = $dojoTrainingFeesByCoord[$coordKey] ?? null;
                            if (is_int($fee) && $fee > 0) {
                                if ($character->getMoney() < $fee) {
                                    // Can't pay for dojo training today.
                                    $dailyLogData['skipped'] = 'dojo_fee_insufficient_funds';
                                    $trainFraction           = 0.0;
                                } else {
                                    $taxRate = $economyCatalog->settlementTaxRate();
                                    $tax     = (int)floor($fee * $taxRate);
                                    if ($tax < 0) {
                                        $tax = 0;
                                    }
                                    if ($tax > $fee) {
                                        $tax = $fee;
                                    }

                                    $character->addMoney(-$fee);

                                    $settlement = $settlementsByCoord[$coordKey] ?? null;
                                    if ($settlement instanceof Settlement && $tax > 0) {
                                        $settlement->addToTreasury($tax);
                                    }

                                    $master = $charactersById[(int)$masterId] ?? null;
                                    if ($master instanceof Character) {
                                        $master->addMoney($fee - $tax);
                                    }

                                    $dailyLogData['dojo_fee_paid'] = $fee;
                                }
                            }
                        }

                        if ($trainFraction > 0.0) {
                            $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier * $trainFraction);
                            $character->applyCoreAttributes($after);
                        }
                    }
                }

                if ($plan->activity === DailyActivity::Travel) {
                    $fromX = $character->getTileX();
                    $fromY = $character->getTileY();

                    $dailyLogData['travel_from_x'] = $fromX;
                    $dailyLogData['travel_from_y'] = $fromY;

                    if (!$character->hasTravelTarget() && $plan->travelTarget instanceof TileCoord) {
                        $character->setTravelTarget($plan->travelTarget->x, $plan->travelTarget->y);
                        $dailyLogData['travel_target_set'] = true;

                        if ($profile instanceof NpcProfile && $profile->getArchetype() === NpcArchetype::Wanderer) {
                            $profile->incrementWanderSequence();
                        }
                    }

                    if ($character->hasTravelTarget()) {
                        $current = new TileCoord($character->getTileX(), $character->getTileY());
                        $target  = new TileCoord((int)$character->getTargetTileX(), (int)$character->getTargetTileY());
                        $next    = $stepper->step($current, $target);
                        $character->setTilePosition($next->x, $next->y);

                        $dailyLogData['travel_target_x'] = $target->x;
                        $dailyLogData['travel_target_y'] = $target->y;
                        $dailyLogData['travel_to_x']     = $next->x;
                        $dailyLogData['travel_to_y']     = $next->y;

                        if ($next->x === $target->x && $next->y === $target->y) {
                            $character->clearTravelTarget();
                            $dailyLogData['travel_arrived'] = true;
                        } else {
                            $dailyLogData['travel_arrived'] = false;
                        }
                    } else {
                        $dailyLogData['travel_arrived'] = false;
                        $dailyLogData['travel_to_x']    = $fromX;
                        $dailyLogData['travel_to_y']    = $fromY;
                    }
                }

                $this->advanceTransformationDay($character, $transformations);

                $emitted[] = new CharacterEvent(
                    world: $world,
                    character: $character,
                    type: 'log.daily_action',
                    day: $day,
                    data: $dailyLogData,
                );
            }

            $this->applyEconomyDay(
                worldDay: $world->getCurrentDay(),
                economyCatalog: $economyCatalog,
                settlementsByCoord: $settlementsByCoord,
                ledgerBySettlement: $workLedger,
            );

            if ($economyCatalog instanceof EconomyCatalog && $settlementsByCoord !== []) {
                $this->enforceSingleMayorPerSettlement($settlementsByCoord, $characters, $economyCatalog);
            }

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
        array $settlementBuildingsByCoord = [],
        array $activeSettlementProjectsByCoord = [],
        array $dojoTrainingMultipliersByCoord = [],
        array $dojoMasterCharacterIdByCoord = [],
        array $dojoTrainingFeesByCoord = [],
        array $settlementTournamentFeedbackByCoord = [],
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
        $charactersById = $this->buildCharacterIndex($characters);

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

            $workLedger = [];

            foreach ($characters as $character) {
                $character->advanceDays(1);

                if ((int)$character->getId() === $playerCharacterId) {
                    $day = $world->getCurrentDay();

                    $dailyLogData = [
                        'activity'      => $trainingMultiplier !== null ? 'train' : 'rest',
                        'work_fraction' => 0.0,
                        'job_code'      => $character->getEmploymentJobCode(),
                        'employment_x'  => $character->getEmploymentSettlementX(),
                        'employment_y'  => $character->getEmploymentSettlementY(),
                        'archetype'     => null,
                    ];

                    if ($trainingMultiplier !== null) {
                        $coordKey = sprintf('%d:%d', $character->getTileX(), $character->getTileY());
                        $masterId = $dojoMasterCharacterIdByCoord[$coordKey] ?? null;
                        $canTrain = true;

                        if ($economyCatalog instanceof EconomyCatalog && is_int($masterId) && $masterId > 0) {
                            $fee = $dojoTrainingFeesByCoord[$coordKey] ?? null;
                            if (is_int($fee) && $fee > 0) {
                                if ($character->getMoney() >= $fee) {
                                    $taxRate = $economyCatalog->settlementTaxRate();
                                    $tax     = (int)floor($fee * $taxRate);
                                    if ($tax < 0) {
                                        $tax = 0;
                                    }
                                    if ($tax > $fee) {
                                        $tax = $fee;
                                    }

                                    $character->addMoney(-$fee);

                                    $settlement = $settlementsByCoord[$coordKey] ?? null;
                                    if ($settlement instanceof Settlement && $tax > 0) {
                                        $settlement->addToTreasury($tax);
                                    }

                                    $master = $charactersById[(int)$masterId] ?? null;
                                    if ($master instanceof Character) {
                                        $master->addMoney($fee - $tax);
                                    }

                                    $dailyLogData['dojo_fee_paid'] = $fee;
                                } else {
                                    // Can't pay for dojo training today; skip training.
                                    $dailyLogData['skipped'] = 'dojo_fee_insufficient_funds';
                                    $canTrain                = false;
                                }
                            }
                        }

                        if ($canTrain) {
                            $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $trainingMultiplier);
                            $character->applyCoreAttributes($after);

                            $dailyLogData['train_fraction']      = 1.0;
                            $dailyLogData['training_multiplier'] = $trainingMultiplier;
                            $dailyLogData['training_context']    = is_int($masterId) && $masterId > 0 ? 'dojo' : 'wilderness';
                        }
                    }

                    $this->advanceTransformationDay($character, $transformations);

                    $emitted[] = new CharacterEvent(
                        world: $world,
                        character: $character,
                        type: 'log.daily_action',
                        day: $day,
                        data: $dailyLogData,
                    );
                    continue;
                }

                $profile = null;
                if ($character->getId() !== null) {
                    $profile = $npcProfilesByCharacterId[(int)$character->getId()] ?? null;
                }

                $plan = null;
                $day = $world->getCurrentDay();

                $dailyLogData                            = [
                    'activity'      => null,
                    'work_fraction' => 0.0,
                    'job_code'      => $character->getEmploymentJobCode(),
                    'employment_x'  => $character->getEmploymentSettlementX(),
                    'employment_y'  => $character->getEmploymentSettlementY(),
                    'archetype'     => $profile instanceof NpcProfile ? $profile->getArchetype()->value : null,
                ];

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
                                context: new GoalContext(
                                    dojoTiles: $dojoTiles,
                                    settlementTiles: $settlementTiles,
                                    settlementsByCoord: $settlementsByCoord,
                                    settlementBuildingsByCoord: $settlementBuildingsByCoord,
                                    activeSettlementProjectsByCoord: $activeSettlementProjectsByCoord,
                                    economyCatalog: $economyCatalog,
                                    settlementTournamentFeedbackByCoord: $settlementTournamentFeedbackByCoord,
                                    events: $events,
                                ),
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
                $dailyLogData['activity']                = $plan->activity->value;
                $dailyLogData['work_fraction']           = $workFraction;
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
                    $coordKey  = sprintf('%d:%d', $character->getTileX(), $character->getTileY());
                    $levelMult = $dojoTrainingMultipliersByCoord[$coordKey] ?? null;

                    if (is_float($levelMult) || is_int($levelMult)) {
                        $multiplier = (float)$levelMult;
                    } else {
                        $multiplier = isset($dojoIndex[$coordKey])
                            ? TrainingContext::Dojo->multiplier()
                            : TrainingContext::Wilderness->multiplier();
                    }

                    $trainFraction = max(0.0, 1.0 - $workFraction);
                    $dailyLogData['train_fraction']      = $trainFraction;
                    $dailyLogData['training_multiplier'] = $multiplier;
                    $dailyLogData['training_context']    = isset($dojoIndex[$coordKey]) ? 'dojo' : 'wilderness';
                    if ($trainFraction > 0.0) {
                        $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier * $trainFraction);
                        $character->applyCoreAttributes($after);
                    }
                }

                if ($plan->activity === DailyActivity::Travel) {
                    $fromX = $character->getTileX();
                    $fromY = $character->getTileY();

                    $dailyLogData['travel_from_x'] = $fromX;
                    $dailyLogData['travel_from_y'] = $fromY;

                    if (!$character->hasTravelTarget() && $plan->travelTarget instanceof TileCoord) {
                        $character->setTravelTarget($plan->travelTarget->x, $plan->travelTarget->y);
                        $dailyLogData['travel_target_set'] = true;

                        if ($profile instanceof NpcProfile && $profile->getArchetype() === NpcArchetype::Wanderer) {
                            $profile->incrementWanderSequence();
                        }
                    }

                    if ($character->hasTravelTarget()) {
                        $current = new TileCoord($character->getTileX(), $character->getTileY());
                        $target  = new TileCoord((int)$character->getTargetTileX(), (int)$character->getTargetTileY());
                        $next    = $stepper->step($current, $target);
                        $character->setTilePosition($next->x, $next->y);

                        $dailyLogData['travel_target_x'] = $target->x;
                        $dailyLogData['travel_target_y'] = $target->y;
                        $dailyLogData['travel_to_x']     = $next->x;
                        $dailyLogData['travel_to_y']     = $next->y;

                        if ($next->x === $target->x && $next->y === $target->y) {
                            $character->clearTravelTarget();
                            $dailyLogData['travel_arrived'] = true;
                        } else {
                            $dailyLogData['travel_arrived'] = false;
                        }
                    } else {
                        $dailyLogData['travel_arrived'] = false;
                        $dailyLogData['travel_to_x']    = $fromX;
                        $dailyLogData['travel_to_y']    = $fromY;
                    }
                }

                $this->advanceTransformationDay($character, $transformations);

                $emitted[] = new CharacterEvent(
                    world: $world,
                    character: $character,
                    type: 'log.daily_action',
                    day: $day,
                    data: $dailyLogData,
                );
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

    /**
     * @param list<Character> $characters
     *
     * @return array<int,Character>
     */
    private function buildCharacterIndex(array $characters): array
    {
        $byId = [];
        foreach ($characters as $character) {
            $id = $character->getId();
            if (is_int($id) && $id > 0) {
                $byId[$id] = $character;
            }
        }

        return $byId;
    }

    /**
     * Enforce: exactly one mayor per settlement at all times.
     *
     * @param array<string,Settlement> $settlementsByCoord
     * @param list<Character>          $characters
     */
    private function enforceSingleMayorPerSettlement(array $settlementsByCoord, array $characters, EconomyCatalog $economyCatalog): void
    {
        $fallbackJob = $this->fallbackNonMayorJob($economyCatalog);

        foreach ($settlementsByCoord as $settlement) {
            $sx = $settlement->getX();
            $sy = $settlement->getY();

            $employedHere = [];
            $onTile       = [];
            $mayorsHere   = [];

            foreach ($characters as $character) {
                if ($character->getTileX() === $sx && $character->getTileY() === $sy) {
                    $onTile[] = $character;
                }

                if (!$character->isEmployed()) {
                    continue;
                }

                if ((int)$character->getEmploymentSettlementX() !== $sx || (int)$character->getEmploymentSettlementY() !== $sy) {
                    continue;
                }

                $employedHere[] = $character;
                if ($character->getEmploymentJobCode() === 'mayor') {
                    $mayorsHere[] = $character;
                }
            }

            if (count($mayorsHere) === 1) {
                continue;
            }

            $candidates = $employedHere !== [] ? $employedHere : $onTile;
            if ($candidates === []) {
                continue;
            }

            $picked = $this->pickMayorCandidate($candidates);
            if ($picked instanceof Character) {
                $picked->setEmployment('mayor', $sx, $sy);

                foreach ($mayorsHere as $mayor) {
                    if ($mayor === $picked) {
                        continue;
                    }

                    if (is_string($fallbackJob)) {
                        $mayor->setEmployment($fallbackJob, $sx, $sy);
                    } else {
                        $mayor->clearEmployment();
                    }
                }
            }
        }
    }

    private function fallbackNonMayorJob(EconomyCatalog $economyCatalog): ?string
    {
        foreach (array_keys($economyCatalog->jobs()) as $code) {
            if (!is_string($code) || $code === '' || $code === 'mayor') {
                continue;
            }

            return $code;
        }

        return null;
    }

    /**
     * @param list<Character> $candidates
     */
    private function pickMayorCandidate(array $candidates): ?Character
    {
        usort($candidates, static function (Character $a, Character $b): int {
            if ($a->getInfluence() !== $b->getInfluence()) {
                return $b->getInfluence() <=> $a->getInfluence();
            }
            if ($a->getMoney() !== $b->getMoney()) {
                return $b->getMoney() <=> $a->getMoney();
            }

            $ai = $a->getId();
            $bi = $b->getId();
            if ($ai === null && $bi === null) {
                return 0;
            }
            if ($ai === null) {
                return 1;
            }
            if ($bi === null) {
                return -1;
            }

            return (int)$ai <=> (int)$bi;
        });

        return $candidates[0] ?? null;
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
