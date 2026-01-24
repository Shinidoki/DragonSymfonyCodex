# Settlement & Character Economy (v1)

## Goal

Enable whole-world simulation where:

- Settlements generate money based on prosperity.
- Characters earn wages only from settlement production (no “money from thin air” after world generation).
- Settlement taxes and retained earnings accumulate in a treasury for future development systems.
- A character’s wages depend on their job and a configurable “work focus” slider.
- Low money can push characters into economy-relevant goals (earn money / find job), without requiring player actions.

## Config

### `config/game/economy.yaml`

Defines:

- Settlement production parameters (per-work-unit production + randomness).
- Wage pool and tax rates.
- Low-money thresholds.
- Jobs (wage weight + work radius).
- Employment pools per NPC archetype for deterministic initial assignment and random job finding later.

### `config/game/goals.yaml`

Adds/uses:

- `work_focus_target` on current goals (0..100).
- `goal.find_job` (travels to a settlement and completes when arriving).
- `money_low_employed` and `money_low_unemployed` event rules to push characters toward `goal.earn_money` or
  `goal.find_job`.

## Data Model

### Character economy fields

`App\Entity\Character` stores:

- `money` and `influence`
- `workFocus` (0..100)
- Employment: `employmentJobCode`, `employmentSettlementX`, `employmentSettlementY`

### Settlement

`App\Entity\Settlement` stores:

- `world`, `x`, `y`
- `prosperity`, `treasury`, `fame`
- `lastSimDayApplied` (guards against double-application)

Settlement creation is ensured from settlement-flagged world tiles and initialized deterministically from the world
seed.

## Daily Simulation Flow (v1)

Within each simulated day:

1. The goal system resolves events and determines the day plan (train/travel/rest).
2. `work_focus_target` is applied from the character’s current goal (if configured).
3. **Work contribution** is calculated for employed characters when:
    - Daily activity is splittable (not travel),
    - The character is within the Manhattan `work_radius` of their employing settlement.
4. Training gain is scaled by `(1 - work_fraction)` (travel remains unsplittable).
5. **Economy tick** runs per settlement using total work contribution:
    - `grossProduction = perWorkUnit(prosperity) * sum(workUnits)` with small randomness,
    - `wagePool = grossProduction * wage_pool_rate`,
    - wages distributed by each worker’s `workUnits`,
    - taxes + retained earnings + rounding leftovers go to `treasury`,
    - net wages go to `Character.money`.
6. If goals are enabled, low-money events are emitted (processed next day):
    - `money_low_unemployed` when unemployed and below threshold,
    - `money_low_employed` when employed and below threshold.

## Money Conservation

After world generation:

- Character money increases only via paid wages from settlement production.
- Settlement treasury increases via retained production + taxes (and wage rounding leftovers).

## Goal Interactions

- `goal.earn_money` increases `work_focus_target` so employed characters shift toward wages.
- `goal.find_job` is used when unemployed and low on money; it travels to the nearest settlement tile in context.
- To avoid “low money” events overriding active goals (e.g. tournament participation), money-low events only apply when
  the character is idle/available.

## Future Extensions

Natural next steps:

- Settlement development spending from treasury (build dojo, markets, defenses).
- Prosperity dynamics (population, worker count, events).
- Tournament lifecycle driven by settlement fame/treasury and organizer influence.

