# Local Turn Scheduler + Combat MVP Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make local mode comply with `docs/time_simulation.md` by (1) enforcing “one action = one tick” across *all* actors
(player + NPCs), (2) introducing a deterministic, speed-based turn scheduler, and (3) adding a minimal deterministic combat
loop so `IntentType::Attack` is no longer just a message.

**Architecture:** Persist per-actor turn state on `LocalActor` (no JSON) and let a `LocalTurnEngine` advance actions in a
single global tick stream. `ApplyLocalActionHandler` becomes “player input + advance until player’s next turn”. Combat is
modeled as a `LocalCombat` aggregate attached to a `LocalSession`, with `LocalCombatant` rows for HP and participation.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit (SQLite in tests).

## Source of Truth (Docs + Clarification)

- `docs/time_simulation.md`: tick = exactly one action; same rules for player + NPC; speed affects action *frequency*.
- `docs/map_locations.md`: combat always happens on a local map; local map runs tick-based simulation.
- User clarifications (2026-01-12): local mode is always optional; encounters are not required; NPC “encounters” happen
  naturally when an NPC reaches a target. Freezing NPCs during long actions is OK for now.

## Non-Goals (This plan)

- Techniques / ki costs / transformations inside combat (`docs/race_techniques.md`, `docs/transformations.md`).
- Injury systems, limb loss, recovery, death handling beyond “defeated” in local combat.
- Background-zone combat resolution (world-map/day simulation).

---

### Task 1: Add deterministic speed-based turn scheduling (domain service)

**Files:**
- Create: `src/Game/Domain/LocalTurns/TurnMeter.php`
- Create: `src/Game/Domain/LocalTurns/TurnScheduler.php`
- Test: `tests/Game/Domain/LocalTurns/TurnSchedulerTest.php`

**Step 1: Write the failing unit test**

- Model 2 actors with different speeds; assert the faster actor is selected more frequently over N ticks.
- Assert tie-break determinism (same inputs → same sequence).

```php
<?php
// tests/Game/Domain/LocalTurns/TurnSchedulerTest.php
namespace App\Tests\Game\Domain\LocalTurns;

use App\Game\Domain\LocalTurns\TurnScheduler;
use PHPUnit\Framework\TestCase;

final class TurnSchedulerTest extends TestCase
{
    public function testFasterActorGetsMoreTurnsDeterministically(): void
    {
        $scheduler = new TurnScheduler(threshold: 100);

        $actors = [
            ['id' => 1, 'speed' => 10, 'meter' => 0],
            ['id' => 2, 'speed' => 30, 'meter' => 0],
        ];

        $counts = [1 => 0, 2 => 0];
        for ($i = 0; $i < 20; $i++) {
            $next = $scheduler->pickNextActorId($actors);
            $counts[$next]++;
        }

        self::assertGreaterThan($counts[1], $counts[2]);
    }
}
```

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/LocalTurns/TurnSchedulerTest.php`
Expected: FAIL (missing classes / methods).

**Step 3: Implement minimal scheduler**

- Use an “initiative meter” algorithm:
  - Every action tick: add `speed` to each actor’s meter.
  - Choose actor with highest meter; tie-break by lowest actor id.
  - Subtract `threshold` from chosen actor’s meter.
- Keep all values integers (deterministic; avoids float drift).

**Step 4: Re-run test**
Expected: PASS.

**Step 5: Commit**
```bash
git add src/Game/Domain/LocalTurns tests/Game/Domain/LocalTurns
git commit -m "feat: add deterministic speed-based turn scheduler"
```

---

### Task 2: Persist per-actor turn meter in the database (no JSON)

**Files:**
- Modify: `src/Entity/LocalActor.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Application/Local/LocalActorTurnMeterPersistenceTest.php`

**Step 1: Write failing integration test**

- Persist a `LocalActor`, set meter, flush, reload, assert the meter is preserved.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalActorTurnMeterPersistenceTest.php`
Expected: FAIL (missing field/column).

**Step 3: Add columns to `LocalActor`**

- `turnMeter` (int, default 0)
- Accessors:
  - `getTurnMeter(): int`
  - `setTurnMeter(int $value): void` (validate non-negative)

**Step 4: Create + run migration**
Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**
```bash
git add src/Entity migrations tests/Game/Application/Local
git commit -m "feat: persist local actor turn meter"
```

---

### Task 3: Introduce a local “turn engine” that advances actions until it’s the player’s turn

**Files:**
- Create: `src/Game/Application/Local/LocalTurnEngine.php`
- Modify: `src/Game/Application/Local/ApplyLocalActionHandler.php`
- Modify: `src/Game/Application/Local/LocalNpcTickRunner.php` (or replace with a new “AI decider”)
- Test: `tests/Game/Application/Local/LocalTurnEngineTest.php`

**Step 1: Write failing test (NPC may act before player if faster)**

- Setup: player speed = 1, NPC speed = 999 with `IntentType::MoveTo` toward some target.
- Call “apply player wait” once.
- Assert:
  - At least one NPC action was processed (NPC moved) either before or after the player action depending on scheduling.
  - `LocalSession.currentTick` equals total actions processed.
  - Turn meters changed deterministically.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalTurnEngineTest.php`
Expected: FAIL (engine missing).

**Step 3: Implement `LocalTurnEngine`**

- Inputs:
  - `LocalSession $session`
  - `LocalAction $playerAction`
- Responsibilities:
  1) Ensure the simulation is advanced until the next actor to act is the player (auto-run NPC turns, emit events).
  2) Apply the player’s action as *one* tick action.
  3) Advance simulation again until the player is next to act.
- Use `TurnScheduler` over the set of `LocalActor`s (player + NPCs).
- Every executed actor action:
  - increments `LocalSession.currentTick` by 1
  - updates the acting `LocalActor.turnMeter` per scheduler rules
  - persists any movement / event effects

**Step 4: Refactor `ApplyLocalActionHandler` to use the engine**

- Keep `ApplyLocalActionHandler::apply(sessionId, action)` signature.
- Move scheduling behavior into the engine to avoid mixing domain logic with CLI concerns.

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**
```bash
git add src/Game/Application/Local tests/Game/Application/Local
git commit -m "feat: add local turn engine with speed-based scheduling"
```

---

### Task 4: Expand local actions to support player-initiated talk + attack

**Files:**
- Modify: `src/Game/Domain/LocalMap/LocalAction.php`
- Modify: `src/Game/Domain/LocalMap/LocalActionType.php`
- Modify: `src/Command/GameLocalActionCommand.php`
- Test: `tests/Game/Application/Local/LocalActionParsingTest.php`

**Step 1: Write failing test for parsing**

- Add tests that `--type=talk --target=<id>` and `--type=attack --target=<id>` are accepted.
- Assert missing `--target` yields `Command::INVALID`.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalActionParsingTest.php`
Expected: FAIL (types not supported).

**Step 3: Implement**

- Add `LocalActionType::Talk` and `LocalActionType::Attack`.
- Update `LocalAction` to carry `?int $targetActorId` (keep it optional for move/wait).
- Update `game:local:action`:
  - `--type` now supports `move|wait|talk|attack`
  - add `--target` option (required for talk/attack)

**Step 4: Re-run tests**
Expected: PASS.

**Step 5: Commit**
```bash
git add src/Game/Domain/LocalMap src/Command tests/Game/Application/Local
git commit -m "feat: add talk/attack local actions"
```

---

### Task 5: Add minimal combat state (HP + defeat) persisted as entities

**Files:**
- Create: `src/Entity/LocalCombat.php`
- Create: `src/Entity/LocalCombatant.php`
- Create: `src/Repository/LocalCombatRepository.php`
- Create: `src/Repository/LocalCombatantRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Application/Local/LocalCombatPersistenceTest.php`

**Step 1: Write failing integration test**

- Persist a `LocalCombat` linked to a `LocalSession`.
- Persist 2 `LocalCombatant`s linked to `LocalCombat` and to actor ids.
- Reload and assert rows exist.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatPersistenceTest.php`
Expected: FAIL (missing entities).

**Step 3: Implement entities**

- `LocalCombat`:
  - `id`
  - `session` (OneToOne or ManyToOne; choose simplest that enforces “0..1 active combat per session”)
  - `status` (`active|resolved`)
  - `startedAt`, `endedAt` (nullable)
- `LocalCombatant`:
  - `id`
  - `combat` (ManyToOne, cascade delete)
  - `actorId` (int, unique per combat)
  - `maxHp` (int)
  - `currentHp` (int)
  - `defeatedAtTick` (nullable int)

**Step 4: Create + run migration**
Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**
```bash
git add src/Entity src/Repository migrations tests/Game/Application/Local
git commit -m "feat: add local combat state entities"
```

---

### Task 6: Implement deterministic attack resolution and hook it into NPC + player actions

**Files:**
- Create: `src/Game/Application/Local/Combat/CombatResolver.php`
- Modify: `src/Game/Application/Local/LocalNpcTickRunner.php` (or new `LocalAiDecider.php`)
- Modify: `src/Game/Application/Local/LocalTurnEngine.php`
- Test: `tests/Game/Application/Local/LocalCombatFlowTest.php`

**Step 1: Write failing flow test (attack reduces HP and can defeat)**

- Setup: session with player + NPC adjacent.
- Start combat by:
  - player `attack` action targeting NPC, or
  - NPC `IntentType::Attack` targeting player.
- Assert:
  - A `LocalCombat` is created (status active).
  - Defender HP decreases deterministically based on attacker strength and defender durability.
  - When HP reaches 0: combatant marked defeated, combat eventually resolves when only one side remains.
  - Nearby event message emitted (e.g. “Krillin attacks Goku for 3 damage.” / “Goku defeats Krillin.”).

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatFlowTest.php`
Expected: FAIL (resolver missing).

**Step 3: Implement minimal deterministic formulas (MVP)**

- Max HP (derived): `maxHp = 10 + (endurance * 2) + durability`
- Damage per hit: `max(1, strength - intdiv(durability, 2))`
- Ignore crits, dodges, ki, techniques, transformations for now.

**Step 4: Wire into the turn engine**

- When an actor performs `LocalActionType::Attack`:
  - validate target exists in same session
  - validate adjacency (Manhattan distance <= 1) (else: no-op + event)
  - ensure combat exists; ensure both participants have combatant rows with computed HP
  - resolve attack: apply damage, mark defeat if HP hits 0
  - emit `LocalEvent` if player is within visibility radius (reuse existing “nearby message” pattern)

**Step 5: Wire NPC `IntentType::Attack` to produce an attack action**

- NPC intent execution should become “decide next action”:
  - if adjacent and intent is attack → `Attack(targetActorId)`
  - else step toward target → `Move(one step)`
  - if invalid target → clear intent

**Step 6: Re-run test**
Expected: PASS.

**Step 7: Commit**
```bash
git add src/Game/Application/Local tests/Game/Application/Local
git commit -m "feat: add deterministic local combat resolution"
```

---

### Task 7: Update CLI/API ergonomics for combat + turns (small QoL)

**Files:**
- Modify: `src/Controller/Api/LocalSessionController.php`
- Create: `src/Command/GameLocalListActorsCommand.php`
- Test: `tests/Game/Application/Local/GameLocalListActorsCommandTest.php`
- (Optional) Update: `README.md`

**Step 1: Add `game:local:list-actors` (failing test first)**

- Output actor id, role, character id, position, turn meter, and (if in combat) HP.

**Step 2: Expose combat state in `GET /api/local-sessions/{id}`**

- Include active combat + combatants list so frontends can render HP + status.

**Step 3: Run relevant tests**
Run:
- `php bin/phpunit tests/Game/Application/Local/GameLocalListActorsCommandTest.php`
- `php bin/phpunit tests/Game/Application/Local`

**Step 4: Commit**
```bash
git add src/Controller src/Command tests/Game/Application/Local README.md
git commit -m "feat: expose local turn/combat state via cli and api"
```

---

## Execution Handoff

Plan complete and saved to `docs/plans/2026-01-12-local-combat-turn-scheduler.md`. Two execution options:

1. Subagent-Driven (this session) — dispatch fresh subagent per task, review between tasks
2. Parallel Session (separate) — new session uses superpowers:executing-plans

Which approach?
