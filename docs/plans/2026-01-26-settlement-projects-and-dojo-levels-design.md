codex resume 019bef8b-31eb-7e72-b314-c18d87e01e06

# Settlement Projects + Dojo Levels (Workforce-Driven) — Design

**Date:** 2026-01-26

## Goal

Add autonomous, simulation-driven settlement development that:

- Spends `Settlement.treasury` over multiple days.
- Progresses based on available daily workforce (work units).
- Diverts workforce from production (so production drops while building).
- Keeps worker wages unchanged by paying the production shortfall from treasury (ongoing workforce/subsidy cost).
- Produces persistent world-state changes (dojo levels) and append-only events.

This is simulation-first (no UI required).

## Existing Context

- `Settlement` exists with `treasury`, `prosperity`, `fame`.
- Daily economy already computes per-settlement work contribution (`work_units`) and distributes wages + taxes.
- `WorldMapTile` has `hasSettlement` and `hasDojo` (boolean).
- Goals/events system exists with append-only `CharacterEvent` and YAML-defined goals.

## High-Level Design

### 1) Buildings (what exists)

Create a generic `SettlementBuilding` entity/table:

- `settlement` (ManyToOne)
- `code` (string, e.g. `dojo`)
- `level` (int, default 0)
- Unique constraint: `(settlement_id, code)`

For dojo:

- `dojo level >= 1` implies `WorldMapTile.hasDojo = true`.

### 2) Projects (what is being built)

Create a `SettlementProject` entity/table:

- `settlement` (ManyToOne)
- `buildingCode` (string; e.g. `dojo`)
- `targetLevel` (int)
- `requiredWorkUnits` (int)
- `progressWorkUnits` (int)
- `status` (string: `active`, `completed`, `canceled`)
- `startedDay` (int)
- `lastSimDayApplied` (int; to guard double-application)

Rule: **max 1 active project per settlement**.

### 3) Mayor/Leader (who can start projects)

Use economy job code:

- Add `mayor` as a job in `config/game/economy.yaml`.
- Enforce: **exactly one mayor per settlement, always**.
- If mayor job is missing/vacated, assign instantly:
    - Candidate pool: characters employed at that settlement; if empty, characters currently on the settlement tile.
    - Ranking: highest `influence`, then highest `money`, then lowest `character_id`.
    - Winner’s employment becomes `mayor` at that settlement (overwrite).

Mayors get their own goal/life-goal behavior (see below).

## Economy & Workforce Model (Key Constraint)

We want workforce diversion **without reducing wages**.

Per settlement per day, given total `work_units_total`:

- Choose `work_units_project` (diverted from production) based on project needs and treasury ability.
- Production uses `work_units_productive = work_units_total - work_units_project`.

Compute wages as if all work units were productive:

- `wagePoolNotional = f(work_units_total, prosperity, randomness, wage_pool_rate)`

Compute actual production and the wage pool it can fund:

- `grossProductionActual = f(work_units_productive, prosperity, randomness)`
- `wagePoolActual = grossProductionActual * wage_pool_rate`

Workers are paid using **wagePoolNotional**.

Settlement pays a **subsidy** from treasury:

- `subsidy = max(0, wagePoolNotional - wagePoolActual)`

If treasury cannot cover `subsidy`, reduce `work_units_project` for that day until subsidy is affordable (wages remain
stable; build slows).

Project progression:

- `progressWorkUnits += work_units_project_effective`

When `progressWorkUnits >= requiredWorkUnits`, project completes.

## Dojo v1: Levels + Multipliers

Dojo levels must be built in order (0→1→2→3).

Training multiplier mapping (when training on settlement tile):

- Dojo level 1: `1.20x`
- Dojo level 2: `1.35x`
- Dojo level 3: `1.50x`

Implementation detail:

- Keep `WorldMapTile.hasDojo` for fast checks, but compute the exact multiplier via
  `SettlementBuilding(code='dojo').level`.

## Configuration

Add a new YAML file for projects/buildings (extensible for future buildings):

- `config/game/projects.yaml`
    - `buildings.dojo.levels.1/2/3.materials_cost`
    - `buildings.dojo.levels.1/2/3.base_required_work_units`
    - Optional tuning: `target_duration_days` (used to scale required work units by workforce so builds “aim to last a
      few days”).
    - `buildings.dojo.levels.*.training_multiplier`

The required work units for a project are computed at project start and stored on the `SettlementProject` row.

## Events (append-only)

Emit broadcast `CharacterEvent`s (character = null):

- `settlement_project_started`
    - `center_x`, `center_y`, `building_code`, `target_level`, `materials_cost`, `required_work_units`
- `settlement_project_completed`
    - `center_x`, `center_y`, `building_code`, `new_level`

(Progress events are optional; skip in v1 to avoid event spam.)

## Goal System Integration (Mayor-driven)

Add a mayor-oriented life goal and current goals:

- Life goal: `leader.lead_settlement`
- Current goal: `goal.start_dojo_project`
    - Behavior:
        - Ensure mayor is at their settlement tile.
        - If there is no active project and dojo level < 3 and treasury buffer allows, emit
          `settlement_project_started`.
        - Goal completes after emitting the event (project lifecycle continues autonomously).

We keep “one current goal per character” semantics.

## Simulation Loop Integration (where logic runs)

Per simulated day:

1) Goal resolver runs (existing).
2) Goal handlers produce daily plans (existing).
3) Travel/train/work and work ledger are computed (existing).
4) Economy tick runs (extend):
    - Before computing production distribution, check active project per settlement.
    - Apply workforce diversion and subsidy logic.
    - Apply project progress (or emit an event for lifecycle service to apply progress).
5) Project completion:
    - Update `SettlementBuilding` level.
    - Flip `WorldMapTile.hasDojo` if dojo reaches level 1.
    - Emit `settlement_project_completed`.
6) Mayor enforcement (exactly one):
    - Run once per day (order can be either before goals or before economy; pick one and keep consistent).

## Testing Targets (v1)

- Integration: project with workers progresses over multiple days and completes; dojo multiplier affects training
  output.
- Integration: with <treasury to subsidize, project slows (but workers still receive same wages).
- Integration: mayor enforcement ensures exactly one mayor per settlement.

## Deferred (Future)

- Additional building codes (market, guardpost, walls) reusing the same building/project framework.
- Multiple concurrent projects (explicitly out of scope for v1).
- Richer mayor AI and political transitions via events.
