# Local Mode + Suspendable Long Actions Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Let the player enter/exit a tile’s local map at will (tick-only simulation) and support long actions (
sleep/train) by temporarily switching to day-resolution world simulation with location-based training bonuses, then
resuming the local map where the player left off.

**Architecture:** Treat “local mode” as an **Active Zone session** persisted in the DB (session + local actor
positions + tick counter). When the player chooses a long action, **suspend** the session, run the existing
day-resolution simulation (with a training modifier), then **resume** the same session and restore the player’s local
position.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

## Source of Truth (Docs)

- `docs/time_simulation.md`: local maps use ticks only; “sleep/train for hours” should fast-forward time (days) instead
  of simulating ticks.
- `docs/map_locations.md`: local map is the Active Zone; world map is macro; transitions should be explicit.
- `docs/character_stats.md` + `docs/power_growth.md`: growth is contextual; mentorship/training context should modify
  growth.

## Assumption (Confirm)

For MVP, when a long action suspends local mode, **the local session’s non-player actors are frozen in place** (no tick
simulation). Only day-level effects (aging/training) happen via world simulation. If you want NPCs to “keep acting”
while the player is away, we’ll need a fast-forward/resync model instead of freezing.

---

### Task 1: Local map primitives (coords, directions, movement)

**Files:**

- Create: `src/Game/Domain/LocalMap/LocalCoord.php`
- Create: `src/Game/Domain/LocalMap/Direction.php`
- Create: `src/Game/Domain/LocalMap/LocalMapSize.php`
- Create: `src/Game/Domain/LocalMap/LocalMovement.php`
- Test: `tests/Game/Domain/LocalMap/LocalMovementTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/LocalMap/LocalMovementTest.php
namespace App\Tests\Game\Domain\LocalMap;

use App\Game\Domain\LocalMap\Direction;
use App\Game\Domain\LocalMap\LocalCoord;
use App\Game\Domain\LocalMap\LocalMapSize;
use App\Game\Domain\LocalMap\LocalMovement;
use PHPUnit\Framework\TestCase;

final class LocalMovementTest extends TestCase
{
    public function testMoveClampsToBounds(): void
    {
        $m = new LocalMovement();
        $size = new LocalMapSize(3, 3);

        $next = $m->move(new LocalCoord(0, 0), Direction::West, $size);
        self::assertSame(0, $next->x);
        self::assertSame(0, $next->y);
    }
}
```

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/LocalMap/LocalMovementTest.php`
Expected: FAIL (classes not found).

**Step 3: Implement minimal primitives**

- `LocalCoord(x,y)` requires `x,y >= 0`
- `LocalMapSize(width,height)` requires `width,height > 0`
- `LocalMovement::move()` returns a new coord; clamps to bounds

**Step 4: Run test to verify it passes**
Run: `php bin/phpunit tests/Game/Domain/LocalMap/LocalMovementTest.php`
Expected: PASS

**Step 5: Commit**

```bash
git add src/Game/Domain/LocalMap tests/Game/Domain/LocalMap
git commit -m "feat: add local map primitives"
```

---

### Task 2: Persist Active Zone session + player local position

**Files:**

- Create: `src/Entity/LocalSession.php`
- Create: `src/Repository/LocalSessionRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Integration/LocalSessionEntityTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/LocalSessionEntityTest.php
namespace App\Tests\Game\Integration;

use App\Entity\LocalSession;
use App\Entity\World;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalSessionEntityTest extends KernelTestCase
{
    public function testLocalSessionIsInstantiable(): void
    {
        self::bootKernel();
        $world = new World('seed-1');
        $session = new LocalSession($worldId: 1, $characterId: 1, tileX: 0, tileY: 0, width: 8, height: 8);
        self::assertSame(8, $session->getWidth());
    }
}
```

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Integration/LocalSessionEntityTest.php`
Expected: FAIL until entity exists.

**Step 3: Implement entity (minimal fields)**
`LocalSession` fields:

- `id`
- `worldId` (int) (MVP: store ids rather than full relations; can refactor later)
- `characterId` (int)
- `tileX`, `tileY` (ints)
- `width`, `height` (ints)
- `playerX`, `playerY` (ints)
- `currentTick` (int, starts 0)
- `status` string/enum: `active|suspended`
- `createdAt`, `updatedAt`

**Step 4: Add migration**
Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`
  Expected: migration succeeds.

**Step 5: Commit**

```bash
git add src/Entity src/Repository migrations tests
git commit -m "feat: persist local active session"
```

---

### Task 3: Enter/exit local mode (CLI)

**Files:**

- Create: `src/Game/Application/Local/EnterLocalModeHandler.php`
- Create: `src/Game/Application/Local/ExitLocalModeHandler.php`
- Create: `src/Command/GameLocalEnterCommand.php`
- Create: `src/Command/GameLocalExitCommand.php`
- Test: `tests/Game/Application/Local/EnterLocalModeHandlerTest.php`

**Step 1: Write the failing test**

- Given a character, handler creates a `LocalSession` at the character’s current tile coords with default size (e.g.
  8x8) and centers the player.

**Step 2: Run failing test**
Run: `php bin/phpunit tests/Game/Application/Local/EnterLocalModeHandlerTest.php`

**Step 3: Implement handlers**

- `EnterLocalModeHandler`:
    - loads `Character` (by id)
    - creates `LocalSession` with `tileX/tileY` from character
    - `playerX/playerY` start at `(width/2,height/2)`
    - ensures only one active session per character (if exists, return it)
- `ExitLocalModeHandler`:
    - marks session as suspended or deletes it (choose **suspended** for MVP so we can resume)

**Step 4: Add CLI commands**

- `php bin/console game:local:enter --character=1 --width=8 --height=8`
- `php bin/console game:local:exit --session=1`

**Step 5: Manual verification**
Expected output:

- enter prints session id and player local coords
- exit prints session id + new status

**Step 6: Commit**

```bash
git add src/Game/Application src/Command tests
git commit -m "feat: add CLI to enter and exit local mode"
```

---

### Task 4: Tick actions in local mode (move/wait)

**Files:**

- Create: `src/Game/Application/Local/ApplyLocalActionHandler.php`
- Create: `src/Game/Domain/LocalMap/LocalActionType.php`
- Create: `src/Game/Domain/LocalMap/LocalAction.php`
- Create: `src/Command/GameLocalActionCommand.php`
- Test: `tests/Game/Application/Local/ApplyLocalActionHandlerTest.php`

**Step 1: Write the failing test**

- Apply `move` north updates `(playerX,playerY)` and increments `currentTick` by 1.

**Step 2: Run failing test**
Run: `php bin/phpunit tests/Game/Application/Local/ApplyLocalActionHandlerTest.php`

**Step 3: Implement**

- Enforce: if session is `suspended`, reject action.
- Action types:
    - `move` + direction
    - `wait`

**Step 4: Add CLI**

- `php bin/console game:local:action --session=1 --type=move --dir=north`
- `php bin/console game:local:action --session=1 --type=wait`

Expected: prints tick number + player coords.

**Step 5: Commit**

```bash
git add src/Game/Application src/Game/Domain src/Command tests
git commit -m "feat: add tick actions for local mode"
```

---

### Task 5: Training context model (dojo/mentor/wilderness) and bonus multiplier

**Files:**

- Create: `src/Game/Domain/Training/TrainingContext.php`
- Modify: `src/Game/Domain/Stats/Growth/TrainingGrowthService.php`
- Test: `tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php` (add multiplier case)

**Step 1: Write the failing test**

- Training with `TrainingContext::Dojo` yields higher STR gain than `TrainingContext::Wilderness` for same intensity.

**Step 2: Run failing test**
Run: `php bin/phpunit tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php`

**Step 3: Implement minimal multiplier**

- Add
  `TrainingGrowthService::trainWithMultiplier(CoreAttributes $before, TrainingIntensity $intensity, float $multiplier): CoreAttributes`
- Keep current `train()` behavior unchanged; call multiplier method with `1.0`.
- `TrainingContext` provides multiplier:
    - `Wilderness` = `1.0`
    - `Dojo` = `1.25`
    - `Mentor` = `1.5`

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain src/Game/Domain/Stats tests/Game/Domain
git commit -m "feat: add training context multipliers"
```

---

### Task 6: Long actions from local mode (sleep/train => suspend => advance days => resume)

**Files:**

- Create: `src/Game/Application/Local/LongActionType.php`
- Create: `src/Game/Application/Local/StartLongActionHandler.php`
- Create: `src/Command/GameLocalTrainCommand.php`
- Create: `src/Command/GameLocalSleepCommand.php`
- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Test: `tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 1: Write the failing test**

- Given a session, calling “train 7 days in dojo”:
    - suspends the session
    - advances world by 7 days
    - resumes session and preserves `(playerX,playerY)`
    - increases character STR more than baseline training

**Step 2: Run failing test**
Run: `php bin/phpunit tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 3: Implement**

- `StartLongActionHandler` does:
    1) load session + character + world
    2) set session status `suspended`
    3) call an extended `AdvanceDayHandler->advanceWithTrainingContext($worldId, $days, $characterId, $context)`
        - MVP scope: apply context multiplier only to the player character; NPCs remain baseline
    4) set session status `active` again
    5) do **not** change player local coords

**Step 4: Add CLI**

- `php bin/console game:local:train --session=1 --days=7 --context=dojo`
- `php bin/console game:local:sleep --session=1 --days=1`

Expected output:

- prints “suspended… advanced… resumed” + updated tiers.

**Step 5: Commit**

```bash
git add src/Game/Application src/Command tests
git commit -m "feat: support suspendable long actions from local mode"
```

---

### Task 7: Location-based training context (dojo/trainer) derived from tile

**Files:**

- Modify: `src/Entity/WorldMapTile.php`
- Create: `src/Game/Application/Local/TrainingContextResolver.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Application/Local/TrainingContextResolverTest.php`

**Step 1: Write the failing test**

- If a tile has `hasDojo=true`, resolver returns `TrainingContext::Dojo`.

**Step 2: Implement minimal tile flag**

- Add `hasDojo` boolean column on `WorldMapTile` (default false).
- `TrainingContextResolver` reads the player’s tile and returns:
    - dojo => `Dojo`
    - otherwise => `Wilderness`

**Step 3: Migrate**
Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

**Step 4: Wire into long action**

- `game:local:train` can accept `--context=auto` (default) and uses resolver.

**Step 5: Commit**

```bash
git add src/Entity src/Game/Application migrations tests
git commit -m "feat: derive training context from tile features"
```

---

### Task 8: Minimal “nearby event messages” scaffolding (for future NPC intent)

**Files:**

- Create: `src/Game/Domain/LocalMap/VisibilityRadius.php`
- Create: `src/Game/Application/Local/LocalEventLog.php`
- Test: `tests/Game/Application/Local/LocalEventLogTest.php`

**Step 1: Write failing test**

- Logging an event within radius returns it in “recent events” for the session.

**Step 2: Implement**

- MVP: store event messages in a `LocalEventLog` DB table or as a JSON column on `LocalSession` (pick one and keep it
  simple).
- `game:local:action` prints any new events after the tick.

**Step 3: Commit**

```bash
git add src/Game src/Entity tests
git commit -m "feat: add local event log for nearby messages"
```

---

### Task 9: README updates + manual flow

**Files:**

- Modify: `README.md`

**Steps**

- Add local mode flow:
    - create world/character
    - generate map
    - enter local mode
    - move a few ticks
    - train 7 days (auto context) and resume local mode

Commands to include:

```bash
php bin/console game:local:enter --character=1 --width=8 --height=8
php bin/console game:local:action --session=1 --type=move --dir=north
php bin/console game:local:train --session=1 --days=7
```

**Commit**

```bash
git add README.md
git commit -m "docs: document local mode and long actions"
```

---

## Explicitly Deferred

- NPC intent resolution inside local map (attack/talk targets) beyond event-log scaffolding.
- Full combat (speed-based scheduler, damage/HP, techniques/transformations in combat).
- Fast-forward/resync model for non-player local actors during suspension.

