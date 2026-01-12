# Local NPC Intents + Nearby Messages Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add minimal tick-level NPC behavior inside local mode: NPCs can be present in a local session, pursue a simple
intent (move toward / talk-to / attack-to), and emit descriptive “nearby messages” when they reach their target—without
requiring an “encounter” to enter local mode.

**Architecture:** Keep local-mode state in Doctrine entities (`LocalSession`, new `LocalActor`, new `LocalIntent`) and
run deterministic tick actions through a small application service that (a) applies the player’s tick action and (b)
advances NPC tick actions as needed. Use `LocalEvent` (separate entity, not JSON columns) to deliver observation
messages; drain and print them after each local action.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

## Source of Truth (Docs + Clarification)

- `docs/map_locations.md`: Player can enter local map voluntarily; encounters can happen during local exploration and
  due to NPC goals.
- `docs/time_simulation.md`: Ticks are the unit of actions; local mode is tick-only.
- `docs/npcs.md`: Active simulation near the player; organic conversations/combat should emerge from NPC intent and
  proximity.
- User clarification (2026-01-12): Local map simulates ticks only. Long actions suspend local mode, advance days, then
  resume local mode at the same local position. NPCs may be frozen during long actions (OK for MVP).

## Non-Goals (This plan)

- Full combat resolution (HP/damage/turn scheduler/techniques/transformations in combat).
- NPC memory/reputation/factions.
- Auto-loading relevant NPCs from world tile population (we’ll add manual CLI wiring first).

---

### Task 1: Persist local actors (participants in a local session)

**Files:**

- Create: `src/Entity/LocalActor.php`
- Create: `src/Repository/LocalActorRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Integration/LocalActorEntityTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/LocalActorEntityTest.php
namespace App\Tests\Game\Integration;

use App\Entity\LocalActor;
use App\Entity\LocalSession;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalActorEntityTest extends KernelTestCase
{
    public function testLocalActorIsInstantiable(): void
    {
        self::bootKernel();

        $session = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 8, height: 8, playerX: 4, playerY: 4);
        $actor = new LocalActor($session, characterId: 1, role: 'player', x: 4, y: 4);

        self::assertSame('player', $actor->getRole());
    }
}
```

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Integration/LocalActorEntityTest.php`
Expected: FAIL (missing classes).

**Step 3: Implement entities**

- `LocalActor` fields:
    - `id`
    - `session` (ManyToOne `LocalSession`, cascade delete)
    - `characterId` (int) (MVP: store id, not relation, to avoid cross-aggregate coupling)
    - `role` (string: `player|npc`)
    - `x`, `y` (local coords)
    - `createdAt`
- Add unique constraint on `(session_id, character_id)` so you can’t add the same character twice to a session.

**Step 4: Create and run migration**
Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

**Step 5: Commit**

```bash
git add src/Entity src/Repository migrations tests
git commit -m "feat: persist local session actors"
```

---

### Task 2: Auto-create player actor when entering local mode

**Files:**

- Modify: `src/Game/Application/Local/EnterLocalModeHandler.php`
- Test: `tests/Game/Application/Local/EnterLocalModeHandlerTest.php`

**Step 1: Write the failing test**

- After `enter()`, there is a `LocalActor` for the session’s player character at `(playerX,playerY)` with role `player`.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/EnterLocalModeHandlerTest.php`
Expected: FAIL until actor is created.

**Step 3: Implement minimal behavior**

- On session create (not reuse), persist a `LocalActor(session, characterId, role=player, x=playerX, y=playerY)`.

**Step 4: Re-run test**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Application/Local tests/Game/Application/Local
git commit -m "feat: create player actor when entering local mode"
```

---

### Task 3: Persist NPC intent (attack/talk/move-to target)

**Files:**

- Create: `src/Game/Domain/LocalNpc/IntentType.php`
- Create: `src/Entity/LocalIntent.php`
- Create: `src/Repository/LocalIntentRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Application/Local/LocalIntentTest.php`

**Step 1: Write the failing test**

- Creating an intent with a target actor id persists and can be loaded for an actor.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Local/LocalIntentTest.php`
Expected: FAIL (classes not found).

**Step 3: Implement**

- `IntentType` enum: `Idle`, `MoveTo`, `TalkTo`, `Attack`
- `LocalIntent` fields:
    - `id`
    - `actor` (ManyToOne `LocalActor`, cascade delete)
    - `type` (enum/string)
    - `targetActorId` (nullable int)
    - `createdAt`
- Repository helper: `findActiveForActor(LocalActor $actor): ?LocalIntent` (MVP: just latest by id).

**Step 4: Migrate**
Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

**Step 5: Commit**

```bash
git add src/Entity src/Repository src/Game/Domain migrations tests
git commit -m "feat: persist local npc intents"
```

---

### Task 4: CLI to add NPC actor and set intent

**Files:**

- Create: `src/Command/GameLocalAddActorCommand.php`
- Create: `src/Command/GameLocalSetIntentCommand.php`
- Test: `tests/Game/Application/Local/LocalIntentFlowTest.php`

**Step 1: Write the failing test**

- Create session, add NPC actor, set its intent toward player actor, verify intent exists in DB.

**Step 2: Run test**
Run: `php bin/phpunit tests/Game/Application/Local/LocalIntentFlowTest.php`

**Step 3: Implement commands**

- `game:local:add-actor --session=1 --character=2 --role=npc --x=0 --y=0`
- `game:local:set-intent --actor=<localActorId> --type=talk_to --target=<playerLocalActorId>`

Expected: prints created actor id / intent id.

**Step 4: Commit**

```bash
git add src/Command tests
git commit -m "feat: add CLI for local actors and intents"
```

---

### Task 5: NPC tick runner (move toward target, emit messages when reaching target)

**Files:**

- Create: `src/Game/Application/Local/LocalNpcTickRunner.php`
- Modify: `src/Game/Application/Local/ApplyLocalActionHandler.php`
- Modify: `src/Game/Application/Local/LocalEventLog.php` (if needed to avoid flushing per event)
- Test: `tests/Game/Application/Local/LocalNpcTickRunnerTest.php`

**Step 1: Write the failing test**

- Setup:
    - session + player actor at (1,1)
    - npc actor at (0,1) with intent `TalkTo -> playerActorId`
- After one player `wait` action:
    - npc takes one tick action (move or resolve)
    - a message is recorded and drained: e.g. `"<npc> talks to <player>."`

**Step 2: Run failing test**
Run: `php bin/phpunit tests/Game/Application/Local/LocalNpcTickRunnerTest.php`

**Step 3: Implement deterministic NPC behavior**
For MVP (no combat):

- If intent is `TalkTo` or `Attack`:
    - If NPC is adjacent to target (Manhattan distance 1), record an event message via `LocalEventLog`:
        - Talk: `"X starts talking to Y."`
        - Attack: `"X attacks Y!"` (no HP effect yet)
    - Else move one step toward target (use `LocalMovement` with a deterministic direction selection: X-first then Y).
- Each NPC action consumes one tick:
    - Increment `LocalSession.currentTick` once per NPC action.

**Step 4: Integrate with player action**

- In `ApplyLocalActionHandler`, after applying the player’s action and incrementing tick, run
  `LocalNpcTickRunner->advanceNpcTurns($sessionId)` which:
    - processes each NPC actor once (or at minimum, processes NPCs with active intents once) and increments tick per
      action
    - emits events and then returns

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Game/Application/Local src/Game/Domain src/Entity tests
git commit -m "feat: add npc tick runner with proximity messages"
```

---

### Task 6: Long actions freeze local actors (verify no NPC movement during train/sleep)

**Files:**

- Modify: `tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 1: Add failing test**

- Create session with an NPC actor that has a travel-to-player intent.
- Call `game:local:train` via handler for N days.
- Assert NPC actor local coords unchanged after the long action.

**Step 2: Run test**
Run: `php bin/phpunit tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 3: Implement (if needed)**

- Ensure `StartLongActionHandler` does not call NPC tick runner; it should only advance days and then resume session.

**Step 4: Commit**

```bash
git add tests/Game/Application/Local/StartLongActionHandlerTest.php
git commit -m "test: ensure long actions freeze local npc positions"
```

---

### Task 7: Expose local session state via API (optional but useful)

**Files:**

- Create: `src/Controller/Api/LocalSessionController.php`
- Test: `tests/Game/Http/LocalSessionApiTest.php`

**Step 1: Write failing HTTP test**

- `GET /api/local-sessions/{id}` returns:
    - session status, tick, tile, size, player coords
    - actors list (id, characterId, role, x, y)

**Step 2: Implement controller**

- Read-only JSON response.

**Step 3: Run test**
Run: `php bin/phpunit tests/Game/Http/LocalSessionApiTest.php`
Expected: PASS.

**Step 4: Commit**

```bash
git add src/Controller tests
git commit -m "feat: expose local session and actors via API"
```

---

### Task 8: README updates (how to see NPC intent messages)

**Files:**

- Modify: `README.md`

**Steps**

- Add example flow:

```bash
php bin/console game:local:enter --character=1 --width=8 --height=8
php bin/console game:local:add-actor --session=1 --character=2 --role=npc --x=0 --y=1
php bin/console game:local:set-intent --actor=<npcActorId> --type=talk_to --target=<playerActorId>
php bin/console game:local:action --session=1 --type=wait
```

- Mention: messages show under the tick output when the player is near the event.

**Commit**

```bash
git add README.md
git commit -m "docs: document local npc intents and nearby messages"
```

