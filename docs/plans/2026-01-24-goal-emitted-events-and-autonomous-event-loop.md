# Goal-Emitted Events + Autonomous Event Loop Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make the background simulation fully autonomous by letting current-goal handlers emit persisted (append-only)
events that affect NPC life goals and current goals starting the *next* day (D+1), without requiring manual
`game:event:emit`.

**Architecture:** Goal handlers return `GoalStepResult` that can include `CharacterEvent` objects to persist.
`AdvanceDayHandler` and `StartLongActionHandler` advance the world **one day at a time**, persisting emitted events and
flushing each day so events have ids and can be processed on the next simulated day.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

---

## Rule Clarification (Locked In)

- **Event reaction timing:** events recorded for day **D** are only processed when resolving goals on day **D+1** (i.e.
  during day resolution for worldDay **D+1**, only events with `event.day < worldDay` apply).

---

### Task 1: Enforce D+1 event processing in the resolver

**Files:**

- Modify: `src/Game/Domain/Goal/CharacterGoalResolver.php`
- Test: `tests/Game/Domain/Goal/CharacterGoalResolverTest.php`

**Step 1: Write the failing test**

Add:

```php
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
            'goal.idle' => ['interruptible' => true, 'defaults' => []],
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
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Goal/CharacterGoalResolverTest.php`
Expected: FAIL because same-day event is currently processed.

**Step 3: Write minimal implementation**

Change the day check to ignore same-day events:

- In `resolveForDay(...)`, change:
    - `if ($event->getDay() > $worldDay) { continue; }`
    - to `if ($event->getDay() >= $worldDay) { continue; }`

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Goal/CharacterGoalResolverTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Game/Domain/Goal/CharacterGoalResolver.php tests/Game/Domain/Goal/CharacterGoalResolverTest.php
git commit -m "feat: process events starting next day"
```

---

### Task 2: Allow event rules to have chance for current-goal overrides (optional but recommended)

**Why:** Not every fighter should react to every broadcast event (e.g. tournaments).

**Files:**

- Modify: `src/Game/Domain/Goal/GoalCatalogLoader.php`
- Modify: `src/Game/Domain/Goal/CharacterGoalResolver.php`
- Test: `tests/Game/Domain/Goal/GoalCatalogLoaderTest.php`
- Test: `tests/Game/Domain/Goal/CharacterGoalResolverTest.php`

**Step 1: Write the failing tests**

1) Loader validation: allow `set_current_goal.chance`:

Add to loader test fixture YAML (or build an inline YAML string) with:

```yaml
event_rules:
    tournament_announced:
        from:
            fighter.become_strongest:
                set_current_goal:
                    code: goal.participate_tournament
                    chance: 0.25
```

Expected: loader should accept it (no exception).

2) Resolver behavior: `set_current_goal.chance: 0.0` must never override:

```php
// arrange a rule with set_current_goal: { code: ..., chance: 0.0 }
// assert current goal remains unchanged after resolveForDay(...)
```

**Step 2: Run tests to verify they fail**

Run:

- `php bin/phpunit tests/Game/Domain/Goal/GoalCatalogLoaderTest.php`
- `php bin/phpunit tests/Game/Domain/Goal/CharacterGoalResolverTest.php`

**Step 3: Write minimal implementation**

1) In `GoalCatalogLoader`, validate `set_current_goal.chance` when present:

- must be int/float, `0.0 <= chance <= 1.0` (clamp to 1.0 if > 1 is acceptable too).

2) In `CharacterGoalResolver::applyCurrentGoalOverrides(...)`:

- If `set_current_goal.chance` is present, roll `random_int(0, 1_000_000)/1_000_000` and return early if roll > chance.
- Only then apply the set.

**Step 4: Re-run tests**

Expected: PASS

**Step 5: Commit**

```bash
git add src/Game/Domain/Goal/GoalCatalogLoader.php src/Game/Domain/Goal/CharacterGoalResolver.php tests/Game/Domain/Goal
git commit -m "feat: add chance for current-goal overrides"
```

---

### Task 3: Let current-goal handlers emit `CharacterEvent`s

**Files:**

- Modify: `src/Game/Domain/Goal/GoalStepResult.php`
- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Modify: `src/Game/Domain/Goal/Handlers/TrainInDojoGoalHandler.php`
- Modify: `src/Game/Domain/Goal/Handlers/ParticipateTournamentGoalHandler.php`
- Modify: `src/Game/Domain/Goal/Handlers/EarnMoneyGoalHandler.php`
- Modify: `src/Game/Domain/Goal/Handlers/WanderGoalHandler.php`

**Step 1: Write the failing test**

Create:

- Test: `tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php` (added in next task)

This will require `GoalStepResult` to support `events`.

**Step 2: Implement `GoalStepResult` event list**

Update `GoalStepResult`:

- Add `public array $events` as `list<CharacterEvent>` with default `[]`
- Keep the new parameter *last* so existing named-argument calls don’t break.

**Step 3: Update `SimulationClock` to collect emitted events**

In both `advanceDays(...)` and `advanceDaysForLongAction(...)`:

- Initialize `$emitted = [];`
- Whenever a goal handler result is produced, merge `$result->events` into `$emitted`.
- Return `$emitted` at the end.

**Step 4: Update existing handlers to pass `events:` when needed**

For now, most handlers will return `events: []` explicitly or rely on the default.

**Step 5: Run targeted tests**

Run: `php bin/phpunit tests/Game/Domain/Goal/Handlers`

**Step 6: Commit**

```bash
git add src/Game/Domain/Goal/GoalStepResult.php src/Game/Domain/Simulation/SimulationClock.php src/Game/Domain/Goal/Handlers tests/Game/Domain/Goal/Handlers
git commit -m "feat: allow goal handlers to emit simulation events"
```

---

### Task 4: Persist emitted events during simulation advances (one day at a time)

**Rationale:** `CharacterGoalResolver` requires `CharacterEvent.id` to track consumption. To process emitted events on
the next simulated day *within the same `--days N` call*, we need to persist + flush them at the end of each day.

**Files:**

- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Modify: `tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`
- Modify: `src/Game/Application/Local/StartLongActionHandler.php`

**Step 1: Write/adjust the failing unit test expectations**

Update `AdvanceDayHandlerTest` to expect:

- `EntityManagerInterface::flush()` called **N times** (once per simulated day) instead of once.
- If you add `persist()` calls for emitted events, assert `persist()` is called (use `expects(self::exactly(...))`).

**Step 2: Implement day-by-day loop in `AdvanceDayHandler::advance()`**

Replace the single `advanceDays(..., days: $days)` call with:

- Build `profilesByCharacterId`, `dojoCoords`, `goalsByCharacterId` once.
- For `$i = 0; $i < $days; $i++`:
    - If goal/event system enabled, load events up to the *current* day:
        - `$events = $this->characterEvents->findByWorldUpToDay($world, $world->getCurrentDay());`
    - `$emitted = $this->clock->advanceDays(... days: 1, events: $events, ...)`
    - `foreach ($emitted as $event) { $this->entityManager->persist($event); }`
    - `$this->entityManager->flush();`

**Step 3: Update `StartLongActionHandler` similarly**

Inside the long action:

- Advance day-by-day and persist emitted events each day.
- Keep the existing suspend/resume behavior.

**Step 4: Run tests**

Run:

- `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`
- `php bin/phpunit tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 5: Commit**

```bash
git add src/Game/Application/Simulation/AdvanceDayHandler.php tests/Game/Application/Simulation/AdvanceDayHandlerTest.php src/Game/Application/Local/StartLongActionHandler.php
git commit -m "feat: persist emitted events during multi-day simulation advance"
```

---

### Task 5: Add a concrete autonomous example: tournaments

**Goal:** Demonstrate a full loop:

- an NPC action emits a broadcast event (`tournament_announced`)
- fighters in range get a current goal override (`goal.participate_tournament`) starting next day

**Files:**

- Modify: `config/game/goals.yaml`
- Create: `src/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandler.php`
- Test: `tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php`
- Test: `tests/Game/Integration/TournamentEventLoopTest.php`

**Step 1: Update YAML catalog**

Add:

- `current_goals.goal.organize_tournament`:
    - `interruptible: true`
    - `handler: App\Game\Domain\Goal\Handlers\OrganizeTournamentGoalHandler`
    - `defaults: { radius: 5 }` (optional)

- `life_goals.civilian.get_rich` (or `civilian.organize_events`) with pool including `goal.organize_tournament`.

- `event_rules.tournament_announced.from.fighter.become_strongest.set_current_goal`:
    - `code: goal.participate_tournament`
    - optional `chance: 0.35` (if Task 2 implemented)

**Step 2: Write the failing handler test**

```php
final class OrganizeTournamentGoalHandlerTest extends TestCase
{
    public function testEmitsTournamentAnnouncementEventAndCompletes(): void
    {
        $world = new World('seed-1');
        $world->advanceDays(1); // world day = 1 (matches how SimulationClock calls handlers)

        $character = new Character($world, 'Announcer', Race::Human);
        $character->setTilePosition(3, 7);

        $handler = new OrganizeTournamentGoalHandler();
        $result = $handler->step($character, $world, ['radius' => 5], new GoalContext([]));

        self::assertTrue($result->completed);
        self::assertCount(1, $result->events);

        $event = $result->events[0];
        self::assertSame('tournament_announced', $event->getType());
        self::assertSame(1, $event->getDay());
        self::assertNull($event->getCharacter(), 'Tournament announcement should be broadcast.');
        self::assertSame(['center_x' => 3, 'center_y' => 7, 'radius' => 5], $event->getData());
    }
}
```

**Step 3: Implement `OrganizeTournamentGoalHandler`**

Minimal behavior:

- Read `radius` from `$data` (int >= 0).
- Emit
  `new CharacterEvent($world, null, 'tournament_announced', $world->getCurrentDay(), ['center_x' => ..., 'center_y' => ..., 'radius' => ...])`.
- Return `DailyPlan(DailyActivity::Rest)` and `completed: true`.

**Step 4: Write the failing integration test**

Create `tests/Game/Integration/TournamentEventLoopTest.php`:

- Boot kernel and reset schema (same pattern as `StartLongActionHandlerTest`).
- Create a world + two characters:
    - Organizer at (3,7) with `CharacterGoal.current_goal_code = goal.organize_tournament`
    - Fighter at (0,0) with life goal `fighter.become_strongest`
- Run `AdvanceDayHandler->advance(worldId, 2)` via container.
- Assert:
    - there is a persisted `CharacterEvent` `tournament_announced` with `day = 1`
    - fighter’s `CharacterGoal.current_goal_code` becomes `goal.participate_tournament` on day 2
    - fighter begins traveling toward (3,7) (tile position or travel target updated)

**Step 5: Run tests**

Run:

- `php bin/phpunit tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php`
- `php bin/phpunit tests/Game/Integration/TournamentEventLoopTest.php`

**Step 6: Commit**

```bash
git add config/game/goals.yaml src/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandler.php tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php tests/Game/Integration/TournamentEventLoopTest.php
git commit -m "feat: add tournament event loop example"
```

---

### Task 6 (Optional): Improve CLI observability for debugging simulation

**Files:**

- Modify: `src/Command/GameSimAdvanceCommand.php`
- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php` (if you want to return emitted events)

**Idea:**

- After advancing, print events created in the day range:
    - `day_start = oldDay + 1`
    - `day_end = oldDay + days`

**Verification:**

- Manual run:
    - `php bin/console game:world:create --seed=seed-1`
    - `php bin/console game:world:generate-map --world=1`
    - `php bin/console game:world:populate --world=1`
    - `php bin/console game:sim:advance --world=1 --days=10`

