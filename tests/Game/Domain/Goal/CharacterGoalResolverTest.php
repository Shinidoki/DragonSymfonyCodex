<?php

namespace App\Tests\Game\Domain\Goal;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\World;
use App\Game\Domain\Goal\CharacterGoalResolver;
use App\Game\Domain\Goal\GoalCatalog;
use App\Game\Domain\Race;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class CharacterGoalResolverTest extends TestCase
{
    public function testMayorUsesLeaderGoalPoolForCurrentGoalSelection(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setEmployment('mayor', 0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.train_in_dojo');
        $goal->setCurrentGoalComplete(true);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => ['current_goal_pool' => [['code' => 'goal.train_in_dojo', 'weight' => 1]]],
                'leader.lead_settlement'   => ['current_goal_pool' => [['code' => 'goal.start_dojo_project', 'weight' => 1]]],
            ],
            currentGoals: [
                'goal.train_in_dojo'      => ['interruptible' => false, 'defaults' => []],
                'goal.start_dojo_project' => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [],
        );

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: []);

        self::assertSame('goal.start_dojo_project', $goal->getCurrentGoalCode());
        self::assertFalse($goal->isCurrentGoalComplete());
    }

    public function testMayorDoesNotInvalidateInProgressNonLeaderGoal(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setEmployment('mayor', 0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.participate_tournament');
        $goal->setCurrentGoalComplete(false);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => ['current_goal_pool' => [['code' => 'goal.participate_tournament', 'weight' => 1]]],
                'leader.lead_settlement'   => ['current_goal_pool' => [['code' => 'goal.start_dojo_project', 'weight' => 1]]],
            ],
            currentGoals: [
                'goal.participate_tournament' => ['interruptible' => false, 'defaults' => []],
                'goal.start_dojo_project'     => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [],
        );

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: []);

        self::assertSame('goal.participate_tournament', $goal->getCurrentGoalCode());
        self::assertFalse($goal->isCurrentGoalComplete());
    }

    public function testIgnoresEventsFromTheSameDay(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.idle');
        $goal->setCurrentGoalComplete(false);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.idle', 'weight' => 1],
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.idle'                   => ['interruptible' => true, 'defaults' => []],
                'goal.participate_tournament' => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [
                'tournament_announced' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'set_current_goal' => ['code' => 'goal.participate_tournament'],
                        ],
                    ],
                ],
            ],
        );

        $event = $this->withId(1, new CharacterEvent($world, null, 'tournament_announced', 5, ['center_x' => 1, 'center_y' => 0, 'radius' => 5]));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 5, events: [$event]);

        self::assertSame(0, $goal->getLastProcessedEventId(), 'Same-day events should not be consumed.');
        self::assertSame('goal.idle', $goal->getCurrentGoalCode(), 'Same-day events should not affect current goals.');
    }

    public function testConsumesEventsAndAllowsAtMostOneLifeGoalChangePerDay(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('civilian.have_family');

        $catalog = new GoalCatalog(
            lifeGoals: [
                'civilian.have_family'     => ['current_goal_pool' => [['code' => 'goal.earn_money', 'weight' => 1]]],
                'fighter.become_strongest' => ['current_goal_pool' => [['code' => 'goal.train_in_dojo', 'weight' => 1]]],
                'wanderer.see_the_world'   => ['current_goal_pool' => [['code' => 'goal.wander', 'weight' => 1]]],
            ],
            currentGoals: [
                'goal.earn_money'    => ['interruptible' => true, 'defaults' => []],
                'goal.train_in_dojo' => ['interruptible' => false, 'defaults' => []],
                'goal.wander'        => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [
                'family_killed'     => [
                    'from' => [
                        'civilian.have_family' => [
                            'chance'      => 1.0,
                            'transitions' => [
                                ['to' => 'fighter.become_strongest', 'weight' => 1],
                            ],
                        ],
                    ],
                ],
                'other_major_event' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'chance'      => 1.0,
                            'transitions' => [
                                ['to' => 'wanderer.see_the_world', 'weight' => 1],
                            ],
                        ],
                    ],
                ],
            ],
        );

        $e1 = $this->withId(10, new CharacterEvent($world, $character, 'family_killed', 0));
        $e2 = $this->withId(11, new CharacterEvent($world, $character, 'other_major_event', 0));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: [$e2, $e1]);

        self::assertSame(11, $goal->getLastProcessedEventId());
        self::assertSame('fighter.become_strongest', $goal->getLifeGoalCode());
        self::assertSame('goal.train_in_dojo', $goal->getCurrentGoalCode());
        self::assertFalse($goal->isCurrentGoalComplete());
        self::assertSame(1, $goal->getLastResolvedDay());
    }

    public function testDoesNotOverrideNonInterruptibleCurrentGoal(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.train_in_dojo');
        $goal->setCurrentGoalComplete(false);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.train_in_dojo', 'weight' => 1],
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.train_in_dojo'          => ['interruptible' => false, 'defaults' => []],
                'goal.participate_tournament' => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [
                'tournament_announced' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'chance'           => 0.0,
                            'transitions'      => [],
                            'set_current_goal' => ['code' => 'goal.participate_tournament', 'data' => ['center_x' => 1, 'center_y' => 0]],
                        ],
                    ],
                ],
            ],
        );

        $event = $this->withId(1, new CharacterEvent($world, null, 'tournament_announced', 0, ['center_x' => 1, 'center_y' => 0, 'radius' => 5]));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: [$event]);

        self::assertSame(1, $goal->getLastProcessedEventId());
        self::assertSame('goal.train_in_dojo', $goal->getCurrentGoalCode());
    }

    public function testDoesNotOverrideCurrentGoalWhenSetCurrentGoalChanceIsZero(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.idle');
        $goal->setCurrentGoalComplete(false);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.idle', 'weight' => 1],
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.idle'                   => ['interruptible' => true, 'defaults' => []],
                'goal.participate_tournament' => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [
                'tournament_announced' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'set_current_goal' => ['code' => 'goal.participate_tournament', 'chance' => 0.0],
                        ],
                    ],
                ],
            ],
        );

        $event = $this->withId(4, new CharacterEvent($world, null, 'tournament_announced', 0, ['center_x' => 1, 'center_y' => 0, 'radius' => 5]));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: [$event]);

        self::assertSame(4, $goal->getLastProcessedEventId());
        self::assertSame('goal.idle', $goal->getCurrentGoalCode());
    }

    public function testWorldEventMayOverrideWhenCurrentGoalIsComplete(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.train_in_dojo');
        $goal->setCurrentGoalComplete(true);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.train_in_dojo', 'weight' => 1],
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.train_in_dojo'          => ['interruptible' => false, 'defaults' => []],
                'goal.participate_tournament' => ['interruptible' => true, 'defaults' => ['foo' => 'bar']],
            ],
            npcLifeGoals: [],
            eventRules: [
                'tournament_announced' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'chance'           => 0.0,
                            'transitions'      => [],
                            'set_current_goal' => ['code' => 'goal.participate_tournament', 'data' => ['center_x' => 1, 'center_y' => 0]],
                        ],
                    ],
                ],
            ],
        );

        $event = $this->withId(2, new CharacterEvent($world, null, 'tournament_announced', 0, ['center_x' => 1, 'center_y' => 0, 'radius' => 5]));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: [$event]);

        self::assertSame(2, $goal->getLastProcessedEventId());
        self::assertSame('goal.participate_tournament', $goal->getCurrentGoalCode());
        self::assertFalse($goal->isCurrentGoalComplete());
        self::assertSame(['foo' => 'bar', 'center_x' => 1, 'center_y' => 0, 'radius' => 5], $goal->getCurrentGoalData());
    }

    public function testBroadcastRadiusUsesManhattanDistance(): void
    {
        $world     = new World('seed-1');
        $character = new Character($world, 'NPC-0001', Race::Human);
        $character->setTilePosition(0, 0);

        $goal = new CharacterGoal($character);
        $goal->setLifeGoalCode('fighter.become_strongest');
        $goal->setCurrentGoalCode('goal.idle');
        $goal->setCurrentGoalComplete(false);

        $catalog = new GoalCatalog(
            lifeGoals: [
                'fighter.become_strongest' => [
                    'current_goal_pool' => [
                        ['code' => 'goal.idle', 'weight' => 1],
                        ['code' => 'goal.participate_tournament', 'weight' => 1],
                    ],
                ],
            ],
            currentGoals: [
                'goal.idle'                   => ['interruptible' => true, 'defaults' => []],
                'goal.participate_tournament' => ['interruptible' => true, 'defaults' => []],
            ],
            npcLifeGoals: [],
            eventRules: [
                'tournament_announced' => [
                    'from' => [
                        'fighter.become_strongest' => [
                            'chance'           => 0.0,
                            'transitions'      => [],
                            'set_current_goal' => ['code' => 'goal.participate_tournament'],
                        ],
                    ],
                ],
            ],
        );

        $event = $this->withId(3, new CharacterEvent($world, null, 'tournament_announced', 0, ['center_x' => 5, 'center_y' => 5, 'radius' => 2]));

        $resolver = new CharacterGoalResolver();
        $resolver->resolveForDay($character, $goal, $catalog, worldDay: 1, events: [$event]);

        self::assertSame(3, $goal->getLastProcessedEventId());
        self::assertSame('goal.idle', $goal->getCurrentGoalCode());
    }

    public function testGoalsCatalogDefinesTournamentInterestCommitRule(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../../../config/game/goals.yaml');

        self::assertIsArray($config['event_rules']['tournament_interest_committed']['from'] ?? null);
        self::assertArrayHasKey('fighter.become_strongest', $config['event_rules']['tournament_interest_committed']['from']);
        self::assertSame(
            'goal.participate_tournament',
            $config['event_rules']['tournament_interest_committed']['from']['fighter.become_strongest']['set_current_goal']['code'] ?? null,
        );
    }

    private function withId(int $id, CharacterEvent $event): CharacterEvent
    {
        $ref = new \ReflectionProperty(CharacterEvent::class, 'id');
        $ref->setAccessible(true);
        $ref->setValue($event, $id);

        return $event;
    }
}
