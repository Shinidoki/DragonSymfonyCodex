# World-Sim Combat Resolver Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace dojo/tournament winner selection with a world-simulation combat resolver that runs round-by-round (
speed-based turns + techniques + charged techniques) without entering local mode.

**Architecture:** Add a pure in-memory combat engine (`SimulatedCombatResolver`) that reuses existing technique math (Ki
cost, success chance, damage calculators) and the existing `TurnScheduler` for speed-based initiative. Integrate it into
`DojoLifecycleService` and `TournamentLifecycleService` so they call it instead of `pickWinner()`.

**Tech Stack:** PHP 8.x, Symfony + Doctrine, PHPUnit.

---

### Task 1: Add RNG abstraction (testable)

**Files:**

- Create: `src/Game/Domain/Random/RandomizerInterface.php`
- Create: `src/Game/Domain/Random/PhpRandomizer.php`
- Create: `src/Game/Domain/Random/SequenceRandomizer.php` (tests)

**Step 1: Write failing test**

- Create: `tests/Game/Domain/Random/SequenceRandomizerTest.php`

**Step 2: Run test to verify it fails**

- Run: `php bin/phpunit tests/Game/Domain/Random/SequenceRandomizerTest.php`

**Step 3: Implement minimal code**

- `SequenceRandomizer` supports deterministic `nextInt()` and `chance()`.

**Step 4: Run test to verify it passes**

---

### Task 2: Implement in-memory simulated combat engine

**Files:**

- Create: `src/Game/Domain/Combat/SimulatedCombat/CombatantState.php`
- Create: `src/Game/Domain/Combat/SimulatedCombat/CombatRules.php`
- Create: `src/Game/Domain/Combat/SimulatedCombat/SimulatedCombatResult.php`
- Create: `src/Game/Domain/Combat/SimulatedCombat/SimulatedCombatResolver.php`

**Step 1: Write failing tests**

- Create: `tests/Game/Domain/Combat/SimulatedCombat/SimulatedCombatResolverTest.php`
    - 1v1: stronger fighter wins under deterministic RNG
    - charged technique: start->charge->ready->release path works
    - teams: friendly-fire disabled prevents friendly targeting

**Step 2: Run tests to verify failure**

- Run: `php bin/phpunit tests/Game/Domain/Combat/SimulatedCombat/SimulatedCombatResolverTest.php`

**Step 3: Implement minimal engine**

- Use `TurnScheduler` for action selection.
- Reuse HP/Ki formulas from local (`CombatResolver` / `LocalTurnEngine`).
- Use `TechniqueUseCalculator::successChance()` + RNG (NOT deterministic roller) for success rolls.
- Use `TechniqueDamageCalculator` for damage.
- Implement charged technique prep/hold behavior consistent with `LocalTurnEngine`.

**Step 4: Run tests to verify pass**

---

### Task 3: Integrate into dojo challenges

**Files:**

- Modify: `src/Game/Application/Dojo/DojoLifecycleService.php`
- Modify: `tests/Game/Integration/DojoMasteryLifecycleTest.php`

**Steps:**

- Replace `pickWinner()` with `SimulatedCombatResolver` call.
- Add optional constructor arg to inject resolver (tests use deterministic RNG).
- Update integration test to inject deterministic resolver.

---

### Task 4: Integrate into tournament resolution

**Files:**

- Modify: `src/Game/Application/Tournament/TournamentLifecycleService.php`
- Modify (if needed): `tests/Game/Integration/*Tournament*.php`

**Steps:**

- Replace `pickWinner()` with `SimulatedCombatResolver` call.
- Add optional constructor arg to inject resolver.

---

### Task 5: Verification

**Steps:**

- Run: `php bin/phpunit`
- Fix only failures caused by this change.

---

**Notes / Constraints**

- World-sim combat is **no-positioning**: technique targeting uses abstract selection (single target vs all enemies),
  not a grid.
- Default production behavior uses **true RNG** (`random_int`). Tests inject deterministic RNG.
