# Technique Targeting + Charged Hold (Data-Driven) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make techniques fully data-driven for targeting/delivery (actor/dir/point/self; projectile/ray/aoe/point) and implement a proper charged technique state machine (charging → ready/holding → release/cancel), including hold Ki drain and allowing transformation during charge only when transformation proficiency ≥ 50.

**Architecture:** Keep global behavior in `TechniqueDefinition.config` and per-character proficiency in `CharacterTechnique`. Extend local-mode actions to carry an aim payload (target actor, direction, or point), and execute via typed `TechniqueExecutor`s. Persist “prepared/charging technique” state on `LocalActor` and “transformation proficiency” via a new `CharacterTransformation` join entity.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

---

### Task 1: Add `CharacterTransformation` join table (proficiency 0..100)

**Files:**
- Create: `src/Entity/CharacterTransformation.php`
- Create: `src/Repository/CharacterTransformationRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Integration/CharacterTransformationEntityTest.php`

**Step 1: Write failing integration test**

- Persist `World`, `Character`, and `CharacterTransformation(character, transformation=super_saiyan, proficiency=51)`, flush, reload, assert.

**Step 2: Run test**
Run: `php bin/phpunit tests/Game/Integration/CharacterTransformationEntityTest.php`
Expected: FAIL (missing classes).

**Step 3: Implement**

- `CharacterTransformation` fields:
  - `id`
  - `character` (ManyToOne `Character`, cascade delete)
  - `transformation` (enum `App\\Game\\Domain\\Transformations\\Transformation`)
  - `proficiency` (int 0..100)
  - `createdAt`, `updatedAt`
- Unique constraint `(character_id, transformation)`.
- Methods: `getProficiency()`, `setProficiency()`.

**Step 4: Migrate**
Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate --no-interaction`

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Repository migrations tests/Game/Integration
git commit -m "feat: persist character transformation proficiency"
```

---

### Task 2: Introduce technique aim payload (actor/dir/point/self) in local actions

**Files:**
- Create: `src/Game/Domain/LocalMap/AimMode.php`
- Modify: `src/Game/Domain/LocalMap/LocalAction.php`
- Modify: `src/Command/GameLocalActionCommand.php`
- Modify: `tests/Game/Application/Local/LocalActionParsingTest.php`

**Step 1: Write failing parsing tests**

- Add cases:
  - `--type=technique --technique=ki_blast --dir=north` is accepted
  - `--type=technique --technique=ki_blast --x=1 --y=2` is accepted
  - `--type=technique --technique=ki_blast` with no aim is accepted only if technique supports `self`

**Step 2: Run tests**
Run: `php bin/phpunit tests/Game/Application/Local/LocalActionParsingTest.php`
Expected: FAIL until CLI/action supports these.

**Step 3: Implement**

- `AimMode` enum: `Self`, `Actor`, `Direction`, `Point`.
- Extend `LocalAction` to allow:
  - `?int $targetActorId`
  - `?Direction $direction`
  - `?int $targetX`, `?int $targetY`
  - `AimMode $aimMode` (or infer based on which fields are present)
- Update `GameLocalActionCommand`:
  - allow `--target` OR `--dir` OR `--x/--y`
  - add options `--x` and `--y`
  - validate mutually exclusive aim options

**Step 4: Re-run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/LocalMap src/Command tests/Game/Application/Local
git commit -m "feat: support technique aim modes in local actions"
```

---

### Task 3: Make technique config declare targeting + delivery shape

**Files:**
- Modify: `docs/data/techniques/ki_blast.json`
- Modify: `docs/data/techniques/kamehameha.json`
- Modify: `src/Game/Application/Techniques/TechniqueImportService.php`
- Test: `tests/Game/Application/Techniques/TechniqueImportServiceValidationTest.php`

**Step 1: Add failing validation tests**

- Import fails if:
  - `aimModes` missing/empty
  - invalid `delivery` value
  - `aoeRadius` missing when `delivery=aoe`
  - `piercing` missing when `delivery=ray`

**Step 2: Implement validation**

Importer validates minimal schema keys inside `config`:
- `aimModes`: list of `self|actor|dir|point`
- `delivery`: `point|projectile|ray|aoe`
- `range`: int >= 0
- `kiCost`: int >= 0
- optional:
  - `piercing`: `first|all` (required for `ray`)
  - `aoeRadius`: int >= 0 (required for `aoe`)
  - `chargeTicks`: int >= 0 (required for `charged`)
  - `holdKiPerTick`: int >= 0 (optional, charged-only)
  - `allowMoveWhilePrepared`: bool (optional)

**Step 3: Update sample JSON**

- `ki_blast`: `aimModes=["actor","dir","point"]`, `delivery="projectile"`, `piercing="first"`.
- `kamehameha`: `aimModes=["actor","dir","point"]`, `delivery="ray"`, `piercing="all"`, `chargeTicks=2`, `holdKiPerTick=1`, `allowMoveWhilePrepared=false`.

**Step 4: Run validation tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Application/Techniques tests/Game/Application/Techniques docs/data/techniques
git commit -m "feat: validate technique targeting and delivery config"
```

---

### Task 4: Implement target selection from aim + delivery (data-driven)

**Files:**
- Create: `src/Game/Domain/Techniques/Targeting/LocalTargetSelector.php`
- Modify: `src/Game/Application/Local/Combat/CombatResolver.php`
- Test: `tests/Game/Domain/Techniques/Targeting/LocalTargetSelectorTest.php`

**Step 1: Write failing unit tests**

- Given a set of actors on grid and a technique config:
  - `delivery=projectile` hits first actor in direction
  - `delivery=ray piercing=all` hits all actors in direction up to range
  - `delivery=aoe` hits all actors within radius around a point
  - `delivery=point` hits actors exactly at point (or none)

**Step 2: Implement selector**

- Input: attacker coord, list of potential target actors (in session), aim, config (range, delivery, piercing, aoeRadius)
- Output: ordered list of actor ids to apply damage to.

**Step 3: Wire into `CombatResolver::useTechnique()`**

- If aim is `actor`: treat as point centered on actor’s current location (still affected by delivery rules).
- If aim is `dir`: use selector ray/projectile rules.
- If aim is `point`: use selector point/aoe rules.
- If aim is `self`: use aoe around attacker or self-only, depending on config.

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Techniques src/Game/Application/Local/Combat tests/Game/Domain/Techniques
git commit -m "feat: data-driven technique targeting and delivery"
```

---

### Task 5: Replace current charging with state machine: charging → ready/hold → release/cancel

**Files:**
- Modify: `src/Entity/LocalActor.php`
- Create: `src/Game/Domain/Techniques/Prepared/PreparedTechniqueState.php`
- Modify: `src/Game/Application/Local/LocalTurnEngine.php`
- Modify: `src/Command/GameLocalActionCommand.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Application/Local/ChargedTechniqueHoldAndReleaseTest.php`

**Step 1: Write failing flow test**

Scenario:
- Character knows `kamehameha` (charged, `chargeTicks=2`, `holdKiPerTick=1`).
- Start technique (no target chosen yet), advance ticks until ready.
- While ready:
  - holding for 1 tick drains hold Ki
  - `release` with `--dir` fires and drains full `kiCost`, damages targets via delivery rules
  - `cancel` clears prepared state and stops drain

**Step 2: Implement prepared state**

Persist on `LocalActor`:
- `preparedTechniqueCode` (nullable string)
- `preparedPhase` (`charging|ready`)
- `preparedTicksRemaining` (int)
- `preparedSinceTick` (int)

Rules:
- If phase `charging`: decrement each actor turn; when reaches 0 → phase becomes `ready`
- If phase `ready`: each actor turn drains `holdKiPerTick` from attacker combatant; if Ki would drop below `kiCost` then auto-cancel (emit event)
- Release requires an aim (actor/dir/point/self) per technique config.
- Cancel spends 1 tick and clears prepared state.

Movement rules:
- allow move/talk/wait while prepared
- move allowed only if config `allowMoveWhilePrepared=true`

**Step 3: Run tests**
Expected: PASS.

**Step 4: Commit**

```bash
git add src/Entity src/Game/Application/Local src/Game/Domain/Techniques migrations tests/Game/Application/Local
git commit -m "feat: charged technique hold and release state machine"
```

---

### Task 6: Allow transformation while prepared if proficiency >= 50

**Files:**
- Modify: `src/Game/Application/Local/LocalTurnEngine.php`
- Modify: `src/Game/Domain/LocalMap/LocalActionType.php`
- Modify: `src/Game/Domain/LocalMap/LocalAction.php`
- Create: `src/Command/GameLocalTransformCommand.php` (or extend `game:local:action --type=transform`)
- Test: `tests/Game/Application/Local/TransformWhileChargingTest.php`

**Steps**
- Add a transform action in local mode that toggles a specified transformation.
- If actor is prepared/charging, allow transform only if `CharacterTransformation.proficiency >= 50`.
- Block other techniques/melee while prepared as per current rules.

**Commit**

```bash
git add src/Game src/Command tests
git commit -m "feat: allow transform while charging with proficiency gate"
```

---

## Execution Handoff

Plan complete and saved to `docs/plans/2026-01-14-technique-targeting-and-charged-hold.md`. Two execution options:

1. Subagent-Driven (this session)
2. Parallel Session (separate)

Which approach?

