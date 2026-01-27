# Settlement Projects + Dojo Levels (Workforce-Driven) — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a simulation-driven, mayor-initiated dojo construction system that diverts workforce from production while
keeping wages stable via a treasury subsidy; dojo levels increase training multipliers.

**Architecture:**

- Persist buildings as generic `SettlementBuilding(code, level)`.
- Persist active builds as `SettlementProject` (one active per settlement).
- Start projects via a mayor current goal that emits an append-only event request; application layer materializes the
  project row from the event.
- Progress projects in the daily economy tick using the already computed settlement work ledger.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit, Symfony YAML.

**Design doc:** `docs/plans/2026-01-26-settlement-projects-and-dojo-levels-design.md`

---

### Task 1: Add projects YAML catalog + provider

**Files:**

- Create: `config/game/projects.yaml`
- Create: `src/Game/Domain/Settlement/ProjectCatalog.php`
- Create: `src/Game/Domain/Settlement/ProjectCatalogLoader.php`
- Create: `src/Game/Application/Settlement/ProjectCatalogProviderInterface.php`
- Create: `src/Game/Application/Settlement/YamlProjectCatalogProvider.php`
- Test: `tests/Game/Domain/Settlement/ProjectCatalogLoaderTest.php`

**Step 1: Write the failing test**

- Create `tests/Game/Domain/Settlement/ProjectCatalogLoaderTest.php` asserting the loader can parse a fixture YAML and
  read dojo level 1..3 data.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Settlement/ProjectCatalogLoaderTest.php`
Expected: FAIL (class not found).

**Step 3: Write minimal implementation**

- `ProjectCatalog` exposes:
    - `dojoLevelDefs(): array` (levels → {materials_cost, base_required_work_units, target_duration_days,
      diversion_fraction, training_multiplier})
    - `dojoTrainingMultiplier(int $level): float`
    - `dojoNextLevel(int $currentLevel): ?int` (0→1→2→3; null at max)
- `ProjectCatalogLoader` loads YAML via Symfony YAML component and validates minimal schema.
- `YamlProjectCatalogProvider` loads from `config/game/projects.yaml`.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Settlement/ProjectCatalogLoaderTest.php`
Expected: PASS.

---

### Task 2: Persist SettlementBuilding and SettlementProject

**Files:**

- Create: `src/Entity/SettlementBuilding.php`
- Create: `src/Repository/SettlementBuildingRepository.php`
- Create: `src/Entity/SettlementProject.php`
- Create: `src/Repository/SettlementProjectRepository.php`
- Create: `migrations/Version*.php` (via doctrine diff)
- Test: `tests/Game/Integration/SettlementProjectSchemaTest.php`

**Step 1: Write failing integration test**

- Create `SettlementProjectSchemaTest` asserting entity classes exist.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectSchemaTest.php`
Expected: FAIL.

**Step 3: Implement entities**

- `SettlementBuilding` (table `game_settlement_building`):
    - `id`
    - ManyToOne `Settlement` (not null)
    - `code` varchar(32)
    - `level` int default 0
    - `createdAt` datetime immutable
    - Unique `(settlement_id, code)`

- `SettlementProject` (table `game_settlement_project`):
    - `id`
    - ManyToOne `Settlement` (not null)
    - `buildingCode` varchar(32)
    - `targetLevel` int
    - `requiredWorkUnits` int
    - `progressWorkUnits` int default 0
    - `status` varchar(16) (`active`, `completed`, `canceled`)
    - `startedDay` int
    - `lastSimDayApplied` int default -1
    - `requestEventId` int nullable, unique
    - `createdAt` datetime immutable
    - Index `(settlement_id, status)`

**Step 4: Generate migration**

Run: `php bin/console doctrine:migrations:diff --no-interaction`
Then inspect/adjust as needed (avoid `datetime_immutable` SQL type issues).

**Step 5: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectSchemaTest.php`
Expected: PASS.

---

### Task 3: Add mayor job and enforce exactly one mayor per settlement

**Files:**

- Modify: `config/game/economy.yaml`
- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Test: `tests/Game/Integration/MayorEnforcementTest.php`

**Step 1: Write failing integration test**

- Create a world with one settlement and two characters employed there.
- Ensure no one is mayor.
- Advance 1 day and assert exactly one character now has `employmentJobCode=mayor`.
- Selection rule: highest influence; tie by highest money; then lowest id.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/MayorEnforcementTest.php`
Expected: FAIL.

**Step 3: Implement mayor job and enforcement**

- Add `jobs.mayor` to economy.yaml.
- In `SimulationClock`, during day processing, enforce mayor per settlement:
    - Find candidates employed at that settlement; if none, candidates currently on settlement tile.
    - Pick best by influence, then money, then id.
    - Overwrite selected candidate’s employment to `mayor` at settlement coords.
    - Demote any other mayors at that settlement to a fallback job.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/MayorEnforcementTest.php`
Expected: PASS.

---

### Task 4: Mayor life goal + current goal to start dojo projects

**Files:**

- Modify: `config/game/goals.yaml`
- Create: `src/Game/Domain/Goal/Handlers/StartDojoProjectGoalHandler.php`
- Modify: `src/Game/Domain/Goal/GoalContext.php` (optional: provide `activeSettlementProjectsByCoord`)
- Modify: `src/Game/Domain/Simulation/SimulationClock.php` (pass active-project info into `GoalContext`)
- Test: `tests/Game/Domain/Goal/Handlers/StartDojoProjectGoalHandlerTest.php`

**Step 1: Write failing unit test**

- If mayor is not at settlement tile, handler returns travel plan.
- If at settlement and no active project, handler emits `settlement_project_started` event with `building_code=dojo`,
  `target_level` and coords.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Goal/Handlers/StartDojoProjectGoalHandlerTest.php`
Expected: FAIL.

**Step 3: Implement minimal goal handler**

- Determine settlement coords from character employment.
- Travel there if needed.
- Check dojo current level (via building map in context OR via event data defaults).
- Emit event (character=null broadcast) requesting project start.
- Complete goal after emitting.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Goal/Handlers/StartDojoProjectGoalHandlerTest.php`
Expected: PASS.

---

### Task 5: Materialize project rows from `settlement_project_started` events

**Files:**

- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Modify: `src/Game/Application/Local/StartLongActionHandler.php`
- Create: `src/Game/Application/Settlement/SettlementProjectLifecycleService.php`
- Test: `tests/Game/Integration/SettlementProjectStartEventLoopTest.php`

**Step 1: Write failing integration test**

- Set up world + settlement + mayor with goal to start dojo project.
- Advance 2 days.
- Assert a `SettlementProject(status=active)` exists, treasury reduced by materials cost.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectStartEventLoopTest.php`
Expected: FAIL.

**Step 3: Implement materialization service**

- Create project if not already created for the `requestEventId`.
- Compute `requiredWorkUnits` using config:
    - `required = max(base_required, estimatedDailyWorkUnits * target_duration_days * diversion_fraction)`
    - Store required on the row.
- Deduct materials cost from treasury.

**Step 4: Wire service into both advance paths**

- After persisting emitted events for the day, call lifecycle service to create projects.

**Step 5: Run tests**

Run: `php bin/phpunit tests/Game/Integration/SettlementProjectStartEventLoopTest.php`
Expected: PASS.

---

### Task 6: Progress projects during economy tick with wage subsidy

**Files:**

- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Test: `tests/Game/Integration/DojoProjectProgressTest.php`

**Step 1: Write failing integration test**

- Create settlement with workers and an active dojo project.
- Advance multiple days.
- Assert:
    - `progressWorkUnits` increases each day.
    - Workers’ wages over a day match the no-project baseline (wage stability).
    - `Settlement.treasury` decreases by subsidy while building.

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/DojoProjectProgressTest.php`
Expected: FAIL.

**Step 3: Implement economy tick changes**

- Compute notional wages using total work units.
- Compute actual production using reduced work units.
- Pay subsidy from treasury to keep wages stable.
- Apply project progress using effective diverted units (reduced if treasury can’t afford subsidy).

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/DojoProjectProgressTest.php`
Expected: PASS.

---

### Task 7: Complete dojo project → building level + training multiplier

**Files:**

- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php` (flip `WorldMapTile.hasDojo` on completion event)
- Modify: `src/Game/Application/Local/StartLongActionHandler.php` (same)
- Test: `tests/Game/Integration/DojoLevelTrainingMultiplierTest.php`

**Step 1: Write failing integration test**

- Create a settlement with an almost-finished dojo project.
- Advance until completion.
- Assert `SettlementBuilding(code=dojo).level` increments.
- Assert a character training on that tile uses the correct multiplier (level 1/2/3).

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/DojoLevelTrainingMultiplierTest.php`
Expected: FAIL.

**Step 3: Implement completion**

- On project completion:
    - mark project completed
    - upsert building level
    - emit `settlement_project_completed` broadcast event
- In application handlers, when `settlement_project_completed` emitted, set the matching `WorldMapTile.hasDojo=true`.
- In training calculation, replace boolean dojo check with multiplier lookup by dojo level.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Integration/DojoLevelTrainingMultiplierTest.php`
Expected: PASS.

---

### Task 8: Full verification

**Step 1: Run full suite**

Run: `php bin/phpunit`
Expected: PASS.

**Step 2: Run migration diff sanity**

Run: `php bin/console doctrine:migrations:diff --no-interaction`
Expected: no new diffs after the migration is committed.
