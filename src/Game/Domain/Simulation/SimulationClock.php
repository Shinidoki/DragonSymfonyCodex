<?php

namespace App\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\World;
use App\Game\Domain\Goal\CharacterGoalResolver;
use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalPlanner;
use App\Game\Domain\Map\TileCoord;
use App\Game\Domain\Map\Travel\StepTowardTarget;
use App\Game\Domain\Npc\DailyActivity;
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
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param list<CharacterEvent>     $events
     */
    public function advanceDays(
        World             $world,
        array             $characters,
        int               $days,
        TrainingIntensity $intensity,
        array             $npcProfilesByCharacterId = [],
        array             $dojoTiles = [],
        array        $goalsByCharacterId = [],
        array        $events = [],
        ?GoalCatalog $goalCatalog = null,
    ): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $planner = $this->dailyPlanner ?? new DailyPlanner();
        $stepper = $this->stepTowardTarget ?? new StepTowardTarget();
        $transformations = $this->transformationService ?? new TransformationService();
        $goalResolver = $this->goalResolver ?? new CharacterGoalResolver();

        $dojoIndex = $this->buildDojoIndex($dojoTiles);

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

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

                    if ($this->goalPlanner instanceof GoalPlanner) {
                        $currentGoalCode = $goal->getCurrentGoalCode();
                        if ($currentGoalCode !== null && !$goal->isCurrentGoalComplete()) {
                            $result = $this->goalPlanner->step(
                                character: $character,
                                world: $world,
                                currentGoalCode: $currentGoalCode,
                                data: $goal->getCurrentGoalData() ?? [],
                                context: new GoalContext($dojoTiles),
                                catalog: $goalCatalog,
                            );

                            $goal->setCurrentGoalData($result->data);
                            $goal->setCurrentGoalComplete($result->completed);
                            $plan = $result->plan;
                        }
                    }
                }

                if ($plan === null) {
                    $plan = $planner->planFor($character, $profile, $dojoTiles);
                }

                if ($plan->activity === DailyActivity::Train) {
                    $multiplier = isset($dojoIndex[sprintf('%d:%d', $character->getTileX(), $character->getTileY())])
                        ? TrainingContext::Dojo->multiplier()
                        : TrainingContext::Wilderness->multiplier();

                    $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier);
                    $character->applyCoreAttributes($after);
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
        }
    }

    /**
     * For MVP: suspendable long actions from local mode.
     * - Player character does NOT travel; they either train with multiplier or rest.
     * - Other characters follow the normal daily planner (train/travel).
     *
     * @param list<Character> $characters
     * @param array<int,NpcProfile> $npcProfilesByCharacterId
     * @param list<TileCoord>       $dojoTiles
     * @param array<int,CharacterGoal> $goalsByCharacterId
     * @param list<CharacterEvent>     $events
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
        array        $goalsByCharacterId = [],
        array        $events = [],
        ?GoalCatalog $goalCatalog = null,
    ): void
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

        $planner = $this->dailyPlanner ?? new DailyPlanner();
        $stepper = $this->stepTowardTarget ?? new StepTowardTarget();
        $transformations = $this->transformationService ?? new TransformationService();
        $goalResolver = $this->goalResolver ?? new CharacterGoalResolver();

        $dojoIndex = $this->buildDojoIndex($dojoTiles);

        for ($i = 0; $i < $days; $i++) {
            $world->advanceDays(1);

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

                    if ($this->goalPlanner instanceof GoalPlanner) {
                        $currentGoalCode = $goal->getCurrentGoalCode();
                        if ($currentGoalCode !== null && !$goal->isCurrentGoalComplete()) {
                            $result = $this->goalPlanner->step(
                                character: $character,
                                world: $world,
                                currentGoalCode: $currentGoalCode,
                                data: $goal->getCurrentGoalData() ?? [],
                                context: new GoalContext($dojoTiles),
                                catalog: $goalCatalog,
                            );

                            $goal->setCurrentGoalData($result->data);
                            $goal->setCurrentGoalComplete($result->completed);
                            $plan = $result->plan;
                        }
                    }
                }

                if ($plan === null) {
                    $plan = $planner->planFor($character, $profile, $dojoTiles);
                }

                if ($plan->activity === DailyActivity::Train) {
                    $multiplier = isset($dojoIndex[sprintf('%d:%d', $character->getTileX(), $character->getTileY())])
                        ? TrainingContext::Dojo->multiplier()
                        : TrainingContext::Wilderness->multiplier();

                    $after = $this->trainingGrowth->trainWithMultiplier($character->getCoreAttributes(), $intensity, $multiplier);
                    $character->applyCoreAttributes($after);
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
        }
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
