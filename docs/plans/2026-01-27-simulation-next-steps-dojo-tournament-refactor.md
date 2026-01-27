# Simulation Next Steps (Dojo Tile Sync, Tournament Guardrails, Context Refactor) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Make world state consistent when projects complete, harden tournament hosting/participation invariants, and
remove duplicated simulation context wiring.

**Architecture:** Keep all behavior in the simulation/application layer (no UI). Project completion emits events;
application handlers apply world-state side effects. Tournament hosting remains event-driven; lifecycle enforces
invariants (1 active per settlement, cancel rules, goal completion). Shared “settlement simulation context” becomes a
small service used by both day-advance paths.

**Tech Stack:** Symfony + Doctrine ORM, PHPUnit, YAML catalogs.

---

### Task 1: Add failing test for dojo completion updating world tile

**Files:**

- Modify: `tests/Game/Integration/SettlementProjectProgressionTest.php`
- (Reference) `src/Game/Application/Settlement/SettlementProjectLifecycleService.php`
- (Reference) `src/Entity/WorldMapTile.php`

**Step 1: Write the failing test**

Add an assertion after the dojo project completes:

```php
$tile = $this->entityManager->getRepository(WorldMapTile::class)->findOneBy([
    'world' => $world,
    'x' => $settlementX,
    'y' => $settlementY,
]);
self::assertTrue($tile->hasDojo(), 'Completing a dojo project must set hasDojo=true on the world tile.');
```

(Use the same world/settlement coords already used in the test setup.)

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectProgressionTest.php -v`

Expected: FAIL with `hasDojo` still false.

---

### Task 2: Implement dojo tile sync when `settlement_project_completed` is emitted

**Files:**

- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Modify: `src/Game/Application/Local/StartLongActionHandler.php`
- (Reference) `src/Game/Application/Settlement/SettlementProjectLifecycleService.php`
- (Reference) `src/Entity/WorldMapTile.php`

**Step 1: Implement minimal side effect**

After persisting the project-lifecycle emitted events, apply the side effect:

- If event `type === 'settlement_project_completed'`
- And `data['building_code'] === 'dojo'`
- Then load the matching `WorldMapTile` for `data['settlement_x'] / data['settlement_y']` and set `hasDojo=true`.

Keep it idempotent (if already true, do nothing).

**Step 2: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectProgressionTest.php -v`

Expected: PASS.

---

### Task 3: Add failing test for “one scheduled tournament per settlement” invariant

**Files:**

- Create: `tests/Game/Integration/TournamentPerSettlementInvariantTest.php`
- (Reference) `src/Game/Application/Tournament/TournamentLifecycleService.php`
- (Reference) `src/Entity/Tournament.php`

**Step 1: Write the failing test**

Scenario:

1) Emit two `tournament_announced` events for the same settlement on consecutive days.
2) Advance the simulation so tournaments would be created.
3) Assert only one `Tournament::STATUS_SCHEDULED` exists for that settlement.

Suggested assertion shape:

```php
$scheduled = $this->entityManager->getRepository(Tournament::class)->findBy([
    'world' => $world,
    'settlement' => $settlement,
    'status' => Tournament::STATUS_SCHEDULED,
]);
self::assertCount(1, $scheduled);
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/TournamentPerSettlementInvariantTest.php -v`

Expected: FAIL (currently likely creates 2).

---

### Task 4: Enforce “one scheduled tournament per settlement” in tournament lifecycle

**Files:**

- Modify: `src/Game/Application/Tournament/TournamentLifecycleService.php`

**Step 1: Implement minimal guard**

When processing `tournament_announced`:

- Before creating a `Tournament`, check whether the settlement already has a scheduled tournament in this world.
- If yes, ignore the new announcement (do not create a second tournament).

Keep it cheap: one query per event (or prefetch scheduled tournaments by settlement id).

**Step 2: Run tests**

Run: `php bin/phpunit tests/Game/Integration/TournamentPerSettlementInvariantTest.php -v`
Expected: PASS.

Run: `php bin/phpunit tests/Game/Integration/TournamentEventLoopTest.php -v`
Expected: PASS.

---

### Task 5: Add regression test for “eliminated participants unlock immediately”

**Files:**

- Create: `tests/Game/Integration/TournamentGoalUnlockOnEliminationTest.php`
- (Reference) `src/Game/Application/Tournament/TournamentLifecycleService.php`
- (Reference) `src/Entity/CharacterGoal.php`

**Step 1: Write the failing test (if needed)**

Scenario:

1) Create a tournament with 4+ participants present.
2) Ensure at least one participant is eliminated during group stage (day `announce_day + 1`).
3) Assert that eliminated participant’s `CharacterGoal->isCurrentGoalComplete()` is `true` after group stage.

If current behavior already satisfies this, keep the test as a regression.

**Step 2: Run test**

Run: `php bin/phpunit tests/Game/Integration/TournamentGoalUnlockOnEliminationTest.php -v`
Expected: PASS (or FAIL if there’s a bug).

---

### Task 6: Optional hardening — ensure organizer targets own settlement when mayor

**Files:**

- Modify: `src/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandler.php`
- Test: `tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php`

**Step 1: Add a failing unit test**

Given a character employed as mayor of settlement (X,Y), ensure target is (X,Y) even if another settlement is nearer.

**Step 2: Implement**

If `Character->getEmploymentSettlement()` exists and `employmentJobCode === 'mayor'`, prefer that settlement tile as
target.

**Step 3: Run tests**

Run: `php bin/phpunit tests/Game/Domain/Goal/Handlers/OrganizeTournamentGoalHandlerTest.php -v`

---

### Task 7: Refactor duplicated “settlement simulation context builder”

**Files:**

- Create: `src/Game/Application/Simulation/SettlementSimulationContextBuilder.php`
- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Modify: `src/Game/Application/Local/StartLongActionHandler.php`
- (Potentially) Modify: `config/services.yaml`

**Step 1: Add a small unit test for builder output**

Create: `tests/Game/Application/Simulation/SettlementSimulationContextBuilderTest.php`

Verify builder returns:

- `settlementBuildingsByCoord`
- `activeSettlementProjectsByCoord`
- `dojoTrainingMultipliersByCoord`

**Step 2: Implement builder**

Move the shared logic currently duplicated in both handlers into the builder.

**Step 3: Swap handlers to use builder**

Replace per-handler ad-hoc assembly with builder call.

**Step 4: Run targeted tests**

Run: `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php -v`
Run: `php bin/phpunit tests/Game/Application/Local/StartLongActionHandlerTest.php -v`
Run: `php bin/phpunit tests/Game/Domain/Simulation/DojoLevelTrainingMultiplierTest.php -v`

---

## Notes / Constraints

- No UI work.
- No migrations expected.
- Do not create commits unless explicitly requested (the original skill suggests frequent commits, but repo rules for
  this session prohibit it).

## Execution Handoff

Plan saved. Two execution options:

1) Subagent-Driven (this session) — REQUIRED SUB-SKILL: superpowers:subagent-driven-development
2) Single-session sequential — REQUIRED SUB-SKILL: superpowers:executing-plans

Which approach?
