<?php

namespace App\Tests\Game\Domain\Simulation;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\World;
use App\Game\Domain\Goal\CurrentGoalHandlerInterface;
use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Goal\GoalContext;
use App\Game\Domain\Goal\GoalPlanner;
use App\Game\Domain\Goal\GoalStepResult;
use App\Game\Domain\Npc\DailyActivity;
use App\Game\Domain\Npc\DailyPlan;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use PHPUnit\Framework\TestCase;

final class SimulationClockEmittedEventsTest extends TestCase
{
    public function testAdvanceDaysReturnsEmittedEventsList(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);

        $clock = new SimulationClock(new TrainingGrowthService());

        $events = $clock->advanceDays($world, [$character], 1, TrainingIntensity::Normal);

        self::assertCount(1, $events);
        self::assertSame('log.daily_action', $events[0]->getType());
        self::assertSame(1, $events[0]->getDay());
        self::assertSame('train', $events[0]->getData()['activity'] ?? null);
    }

    public function testAdvanceDaysCollectsEventsEmittedByCurrentGoalHandler(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $this->setEntityId($character, 1);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('test.life');
        $goal->setCurrentGoalCode('goal.emit_event');
        $goal->setCurrentGoalComplete(false);

        $handler = new class implements CurrentGoalHandlerInterface {
            public function step(Character $character, World $world, array $data, GoalContext $context): GoalStepResult
            {
                return new GoalStepResult(
                    plan: new DailyPlan(DailyActivity::Rest),
                    data: $data,
                    completed: true,
                    events: [
                        new CharacterEvent(
                            world: $world,
                            character: null,
                            type: 'test.event',
                            day: $world->getCurrentDay(),
                            data: ['foo' => 'bar'],
                        ),
                    ],
                );
            }
        };

        $catalog = new GoalCatalog(
            lifeGoals: [
                'test.life' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.emit_event', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.emit_event' => [
                    'interruptible' => true,
                    'defaults'      => [],
                    'handler'       => $handler::class,
                ],
            ],
            npcLifeGoals: [],
            eventRules: [],
        );

        $clock = new SimulationClock(
            new TrainingGrowthService(),
            goalPlanner: new GoalPlanner([$handler]),
        );

        $events = $clock->advanceDays(
            world: $world,
            characters: [$character],
            days: 1,
            intensity: TrainingIntensity::Normal,
            goalsByCharacterId: [1 => $goal],
            events: [],
            goalCatalog: $catalog,
        );

        self::assertCount(2, $events);

        $types = array_map(static fn(CharacterEvent $e): string => $e->getType(), $events);
        sort($types);
        self::assertSame(['log.daily_action', 'test.event'], $types);
    }

    private function setEntityId(object $entity, int $id): void
    {
        $ref = new \ReflectionProperty($entity::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($entity, $id);
    }
}
