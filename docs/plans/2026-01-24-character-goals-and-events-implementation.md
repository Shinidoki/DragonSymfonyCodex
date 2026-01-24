# Character Goals and Events Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a simulation-first system where every character has a persisted life goal + one persisted current goal,
driven by a YAML catalog and updated daily via append-only character/world events.

**Architecture:** Persist goals in a 1:1 `CharacterGoal` table and events in an append-only `CharacterEvent` table (
world-wide or character-specific). Load a YAML goal catalog, resolve events at the start of each simulated day (at most
one life-goal change per day, consume all events), then let current-goal handlers produce a `DailyPlan` that drives
`SimulationClock` behavior (train/travel/rest).

**Tech Stack:** Symfony, Doctrine ORM + migrations, PHPUnit, Symfony YAML.

**Design doc:** `docs/plans/2026-01-24-character-life-goals-current-goals-and-events-design.md`

---

### Task 1: Add YAML goal catalog skeleton + loader

**Files:**

- Create: `config/game/goals.yaml`
- Create: `src/Game/Domain/Goal/GoalCatalog.php`
- Create: `src/Game/Domain/Goal/GoalCatalogLoader.php`
- Test: `tests/Game/Domain/Goal/GoalCatalogLoaderTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/Goal/GoalCatalogLoaderTest.php
namespace App\Tests\Game\Domain\Goal;

use App\Game\Domain\Goal\GoalCatalogLoader;
use PHPUnit\Framework\TestCase;

final class GoalCatalogLoaderTest extends TestCase
{
    public function testLoadsYamlAndExposesLifeGoalsAndCurrentGoals(): void
    {
        $loader = new GoalCatalogLoader();
        $catalog = $loader->loadFromFile(__DIR__ . '/fixtures/goals.yaml');

        self::assertNotEmpty($catalog->lifeGoals());
        self::assertNotEmpty($catalog->currentGoals());
    }
}
```

Also create fixture:

- `tests/Game/Domain/Goal/fixtures/goals.yaml` with:
    - at least one `life_goals.*.current_goal_pool` entry
    - at least one `current_goals.*.interruptible` entry
    - at least one `npc_life_goals.fighter` entry

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Goal/GoalCatalogLoaderTest.php -v`
Expected: FAIL (class not found).

**Step 3: Write minimal implementation**

- `GoalCatalog` stores parsed arrays and provides helpers:
    - `lifeGoals(): array`
    - `currentGoals(): array`
    - `lifeGoalPool(string $lifeGoalCode): array`
    - `currentGoalInterruptible(string $currentGoalCode): bool`
    - `isCurrentGoalCompatible(string $lifeGoalCode, string $currentGoalCode): bool`
- `GoalCatalogLoader` loads YAML via Symfony YAML component and validates the minimal schema.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Goal/GoalCatalogLoaderTest.php -v`
Expected: PASS.

**Step 5: Commit**

```bash
git add config/game/goals.yaml src/Game/Domain/Goal tests/Game/Domain/Goal
git commit -m "feat: add goals YAML catalog loader"
```

---

### Task 2: Add CharacterGoal + CharacterEvent entities and repositories

**Files:**

- Create: `src/Entity/CharacterGoal.php`
- Create: `src/Repository/CharacterGoalRepository.php`
- Create: `src/Entity/CharacterEvent.php`
- Create: `src/Repository/CharacterEventRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Integration/CharacterGoalsSchemaTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/CharacterGoalsSchemaTest.php
namespace App\Tests\Game\Integration;

use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CharacterGoalsSchemaTest extends KernelTestCase
{
    public function testEntitiesAreDiscoverable(): void
    {
        self::bootKernel();
        self::assertTrue(class_exists(CharacterGoal::class));
        self::assertTrue(class_exists(CharacterEvent::class));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/CharacterGoalsSchemaTest.php -v`
Expected: FAIL (classes not found).

**Step 3: Implement entities (minimal, per design)**

- `CharacterGoal` (table `game_character_goal`)
    - OneToOne `Character` (unique)
    - `lifeGoalCode` string nullable
    - `currentGoalCode` string nullable
    - `currentGoalData` json nullable (Doctrine `json`)
    - `currentGoalComplete` bool default false
    - `lastResolvedDay` int default 0
    - `lastProcessedEventId` int default 0

- `CharacterEvent` (table `game_character_event`)
    - ManyToOne `World` (not null)
    - ManyToOne `Character` (nullable) — `null` means broadcast/world event
    - `type` string
    - `day` int
    - `createdAt` datetime immutable
    - `data` json nullable

Add repositories with targeted queries:

- `CharacterGoalRepository::findByWorld(World $world): array` (join through character.world)
- `CharacterEventRepository::findForResolver(World $world, int $characterId, int $maxDay, int $minIdExclusive): array`
    - loads events where:
        - `world = :world`
        - `day <= :maxDay`
        - `id > :minId`
        - `(character = :character OR character IS NULL)`

**Step 4: Create and run migration**

Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`
  Expected: Migration runs with exit code 0.

**Step 5: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/CharacterGoalsSchemaTest.php -v`
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Repository migrations tests/Game/Integration
git commit -m "feat: add character goals and events tables"
```

---

### Task 3: Assign NPC starting life goals when populating the world

**Files:**

- Modify: `src/Game/Application/World/PopulateWorldHandler.php`
- Modify: `tests/Game/Application/World/PopulateWorldHandlerTest.php`
- Modify: `config/game/goals.yaml`
- Create: `src/Game/Application/World/NpcLifeGoalPicker.php`

**Step 1: Extend failing test**

In `tests/Game/Application/World/PopulateWorldHandlerTest.php`, after populating 10 NPCs:

- Assert 10 `CharacterGoal` rows exist (one per character created).
- Assert each has non-empty `life_goal_code`.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Application/World/PopulateWorldHandlerTest.php -v`
Expected: FAIL (no `CharacterGoal` rows).

**Step 3: Implement minimal picker + persistence**

- Add `npc_life_goals` to `config/game/goals.yaml`, e.g.:
    - `npc_life_goals.civilian: [...]`
    - `npc_life_goals.fighter: [...]`
    - `npc_life_goals.wanderer: [...]`
- Create `NpcLifeGoalPicker`:
    - loads the catalog
    - picks a `life_goal_code` by weight (true randomness)
    - returns a string code
- In `PopulateWorldHandler`:
    - create `CharacterGoal` for each created NPC character
    - set `life_goal_code` based on `NpcProfile.archetype`
    - set `current_goal_code = null`, `current_goal_complete = false`

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Application/World/PopulateWorldHandlerTest.php -v`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Application/World config/game/goals.yaml tests/Game/Application/World
git commit -m "feat: assign starting life goals for populated NPCs"
```

---

### Task 4: Implement daily goal resolver (events + compatibility + interruptibility)

**Files:**

- Create: `src/Game/Domain/Goal/CharacterGoalResolver.php`
- Create: `src/Game/Domain/Goal/EventRuleMatcher.php`
- Test: `tests/Game/Domain/Goal/CharacterGoalResolverTest.php`

**Step 1: Write the failing unit test (core rules)**

Test scenarios:

- Consumes events by id and updates `last_processed_event_id`.
- At most one life-goal change per day, but still consumes subsequent events.
- `set_current_goal` respects:
    - compatibility with (possibly updated) `life_goal_code` pool
    - interruptible flag when a goal is incomplete
- Broadcast event radius uses Manhattan distance.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Goal/CharacterGoalResolverTest.php -v`
Expected: FAIL (classes not found).

**Step 3: Implement minimal resolver**

Model inputs so resolver stays pure:

- Input: `Character $character`, `CharacterGoal $goal`, `GoalCatalog $catalog`, `int $worldDay`, `array $events`
- Output: mutates `$goal` (life/current goals, JSON, completion flag, last_processed_event_id, last_resolved_day)

YAML rule schema (extend `config/game/goals.yaml`):

- `event_rules.<event_type>.from.<life_goal_code>`:
    - `chance` (0..1)
    - `transitions`: list of `{ to, weight }` (life-goal change; max 1/day)
    - `set_current_goal` (optional): `{ code, data }`
    - `clear_current_goal` (optional): `true`

Resolver order per event:

1) If life goal not changed today, apply conditional transition (chance + weighted `to`).
2) Then apply clear/set current goal:
    - If `current_goal_code` is set and incomplete, require `interruptible=true` to clear/set.
    - If goal is missing or complete, allow clear/set.
    - Any `set_current_goal.code` must be compatible with current `life_goal_code`.
3) Always consume event by advancing `last_processed_event_id` (even if rule doesn’t apply).

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Goal/CharacterGoalResolverTest.php -v`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Goal tests/Game/Domain/Goal config/game/goals.yaml
git commit -m "feat: resolve character goals from daily events"
```

---

### Task 5: Add current-goal handler interface + first two handlers

**Files:**

- Create: `src/Game/Domain/Goal/CurrentGoalHandlerInterface.php`
- Create: `src/Game/Domain/Goal/GoalContext.php`
- Create: `src/Game/Domain/Goal/GoalStepResult.php`
- Create: `src/Game/Domain/Goal/Handlers/TrainInDojoGoalHandler.php`
- Create: `src/Game/Domain/Goal/Handlers/ParticipateTournamentGoalHandler.php`
- Test: `tests/Game/Domain/Goal/Handlers/TrainInDojoGoalHandlerTest.php`
- Test: `tests/Game/Domain/Goal/Handlers/ParticipateTournamentGoalHandlerTest.php`

**Step 1: Write failing tests**

- `TrainInDojoGoalHandler`:
    - If not on dojo tile, returns `DailyActivity::Travel` and sets/keeps a travel target for nearest dojo.
    - If on dojo tile, returns `DailyActivity::Train` and increments `days_trained` in JSON.
    - Marks complete when `days_trained >= target_days`.

- `ParticipateTournamentGoalHandler`:
    - Uses event-provided `current_goal_data` containing `center_x`, `center_y` as the target.
    - Returns `Travel` until reached; then marks complete.

**Step 2: Run tests to verify they fail**

Run:

- `php bin/phpunit tests/Game/Domain/Goal/Handlers/TrainInDojoGoalHandlerTest.php -v`
- `php bin/phpunit tests/Game/Domain/Goal/Handlers/ParticipateTournamentGoalHandlerTest.php -v`
  Expected: FAIL (classes not found).

**Step 3: Implement minimal handlers**

Add corresponding `current_goals` entries in `config/game/goals.yaml`:

- `goal.train_in_dojo` with `interruptible: false` and defaults like `target_days: 7`
- `goal.participate_tournament` with `interruptible: true`

**Step 4: Run tests to verify they pass**

Run same commands
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Goal/Handlers src/Game/Domain/Goal config/game/goals.yaml tests/Game/Domain/Goal/Handlers
git commit -m "feat: add initial current-goal handlers"
```

---

### Task 6: Add goal planner/dispatcher and wire into SimulationClock (goals primary)

**Files:**

- Create: `src/Game/Domain/Goal/GoalPlanner.php`
- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Modify: `src/Game/Application/Local/StartLongActionHandler.php`
- Test: `tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`

**Step 1: Extend failing test**

Add a scenario to `AdvanceDayHandlerTest`:

- Create a world + one character with a `CharacterGoal` whose `current_goal_code = goal.participate_tournament`
  targeting `(1,0)`.
- Advance 1 day.
- Assert the character moved toward `(1,0)` (and did not train).

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php -v`
Expected: FAIL (clock ignores goals).

**Step 3: Implement integration**

- `GoalPlanner`:
    - Given a character, its `CharacterGoal`, dojo coords, and catalog:
        - If `current_goal_code` exists and not complete, dispatch to handler by code and return its `DailyPlan`.
        - Else fallback to existing archetype planner (`DailyPlanner`) behavior.

- `SimulationClock`:
    - Accept an optional `GoalPlanner` dependency (similar to existing optional planner/stepper).
    - Add optional parameters to `advanceDays(...)` and `advanceDaysForLongAction(...)`:
        - `goalsByCharacterId: array<int,CharacterGoal>`
        - `events: list<CharacterEvent>`
        - `goalCatalog: ?GoalCatalog`
    - At start of each day per character:
        - Run `CharacterGoalResolver` (events up to the current day, targeted + broadcast within radius).
        - If goal needs a new current goal, pick from life goal pool (true randomness) and persist on the entity.
    - Use `GoalPlanner` to decide plan and then execute plan (existing travel/train logic).

- Handlers:
    - Inject repositories to `AdvanceDayHandler` / `StartLongActionHandler` to load:
        - `CharacterGoal` rows for all world characters (map by character id)
        - `CharacterEvent` rows for `world` up to `worldDay + days` (so future-day events are available as the day
          increments)

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php -v`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Simulation src/Game/Application/Simulation src/Game/Application/Local tests/Game/Application/Simulation
git commit -m "feat: drive daily simulation from character goals"
```

---

### Task 7: Add an event emitter command (dev-only simulation tool)

**Files:**

- Create: `src/Command/GameEventEmitCommand.php`
- Modify: `config/game/goals.yaml`

**Step 1: Implement command**

Command: `game:event:emit`

- Options:
    - `--world` (required)
    - `--type` (required)
    - `--day` (optional; default current world day)
    - `--character` (optional; omit for broadcast)
    - `--center-x`, `--center-y`, `--radius` (optional; broadcast filter)
    - `--data` (optional JSON string; merged into event `data`)

**Step 2: Smoke test command manually**

Run:

- `php bin/console game:event:emit --world=1 --type=tournament_announced --center-x=4 --center-y=4 --radius=3`
  Expected: Prints created event id.

**Step 3: Commit**

```bash
git add src/Command
git commit -m "feat: add CLI to emit character/world events"
```

---

### Task 8: Verification

**Step 1: Run full PHPUnit**

Run: `php bin/phpunit`
Expected: exit code 0.

