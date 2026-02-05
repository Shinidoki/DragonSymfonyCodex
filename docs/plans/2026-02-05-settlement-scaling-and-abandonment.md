# Settlement Scaling + Abandonment Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Prevent worlds from stagnating due to every character becoming a mayor by (1) capping settlements relative to
population and (2) abandoning under-populated settlements automatically.

**Architecture:** Settlement tiles are generated on the map, then *pruned* during NPC population to ensure the number of
active settlements is proportional to population. During simulation, settlements with too few residents/workers are
marked abandoned by clearing `hasSettlement` (and `hasDojo`) on their map tile and deleting the persisted `Settlement`
entity.

**Tech Stack:** Symfony + Doctrine ORM, PHPUnit, domain simulation in `SimulationClock`.

## Task 1: Add failing test for settlement cap + mayor distribution

**Files:**

- Create: `tests/Game/Integration/SettlementScalingTest.php`

**Step 1: Write failing test**

- Generate a world + map (16x16) with a deterministic seed
- Populate with 25 NPCs
- Assert `hasSettlement=true` tile count is `<= 5` (floor(25/5))
- Advance simulation by 1 day
- Assert not all characters are mayors (and mayor count is `<= settlementCount`)

**Step 2: Run test to verify it fails**

- Run: `php bin/phpunit tests/Game/Integration/SettlementScalingTest.php`
- Expected: FAIL because settlement tiles are not capped yet (too many settlements → too many mayors).

## Task 2: Implement settlement pruning in world population

**Files:**

- Modify: `src/Game/Application/World/PopulateWorldHandler.php`

**Step 1: Add pruning logic**

- Before picking start positions/employers, load settlement tiles.
- If settlement tiles exceed target `max(1, floor(totalPopulation/5))`, deterministically keep a subset:
    - Always keep (0,0) if present
    - Keep remaining highest-ranked tiles by a seed+coord hash
    - For all others: set `hasSettlement=false` and `hasDojo=false`
- Re-load settlements/dojos after pruning and continue existing NPC creation logic.

**Step 2: Run the test**

- Run: `php bin/phpunit tests/Game/Integration/SettlementScalingTest.php`
- Expected: PASS.

## Task 3: Add failing test for abandoning under-populated settlements

**Files:**

- Create: `tests/Game/Integration/SettlementAbandonmentTest.php`

**Step 1: Write failing test**

- Create a world with map size
- Create a settlement tile at (3,0) with `hasSettlement=true`
- Add 4 characters on tile and employed there (population=4)
- Advance simulation by 1 day
- Assert tile is no longer a settlement (`hasSettlement=false`) and `SettlementRepository::findOneByWorldCoord(...)`
  returns null
- Assert characters employed there have employment cleared

**Step 2: Run test to verify it fails**

- Run: `php bin/phpunit tests/Game/Integration/SettlementAbandonmentTest.php`
- Expected: FAIL until abandonment is implemented.

## Task 4: Implement abandonment in simulation advance handler

**Files:**

- Modify: `src/Game/Application/Simulation/AdvanceDayHandler.php`

**Step 1: Add abandonment pass after each simulated day**

- Only run when `SettlementRepository` is available.
- Compute per-settlement population as the unique union of:
    - characters currently on the settlement tile, and
    - characters employed to the settlement coord.
- If population `< 5`, mark the tile abandoned and delete the `Settlement` entity:
    - `WorldMapTile::setHasSettlement(false)` and `setHasDojo(false)`
    - clear employment for characters employed there
    - remove `Settlement` entity (DB cascades to projects/buildings)
- In the per-day loop path, refresh settlement coords/entities after abandonment for the next day.

**Step 2: Run tests**

- Run: `php bin/phpunit tests/Game/Integration/SettlementAbandonmentTest.php`
- Expected: PASS.

## Task 5: Full verification

**Files:**

- (none)

**Step 1: Run focused suite**

- Run: `php bin/phpunit tests/Game/Integration`

**Step 2: Run full suite (optional)**

- Run: `php bin/phpunit`

