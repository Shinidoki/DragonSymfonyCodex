# Character Life Goals, Current Goals, and Events (Design)

## Goal

Introduce a simulation-first goal system where **every character** (NPC + player) has:

- An overarching **life goal** (`life_goal_code`)
- Exactly **one active current goal** at a time (`current_goal_code` + `current_goal_data`)

Life goals influence which current goals are eligible. Current goals drive day-by-day behavior (train / travel / rest).
Life goals can change due to **append-only events**, resolved at the start of each simulated day.

This design intentionally de-prioritizes player interaction/UI; it focuses on deterministic state transitions and
queryable storage for simulation.

## Storage

### `game_character_goal` (1:1 with `game_character`)

Keep `game_character` slim by storing goals in a dedicated table.

Columns (proposed):

- `id`
- `character_id` (unique, FK to `game_character`, cascade delete)
- `life_goal_code` (string, nullable in DB; required by player creation flow)
- `current_goal_code` (string, nullable)
- `current_goal_data` (JSON, nullable)
- `current_goal_complete` (bool, default false) — queryable completion flag
- `last_resolved_day` (int, default 0) — prevents double resolution within the same day
- `last_processed_event_id` (int, default 0) — idempotent event consumption

Indexes:

- `uniq_character_goal_character` on `character_id`
- Optional: `(life_goal_code)` and `(current_goal_code, current_goal_complete)` if needed for dashboard queries

### `game_character_event` (append-only; “world event” or “character event”)

Supports:

- Events targeting a specific character (`character_id` set)
- Broadcast/world events (`character_id` is null), optionally scoped by location/radius in `data`

Columns (proposed):

- `id`
- `world_id` (FK to `world`)
- `character_id` (nullable FK to `game_character`)
- `type` (string, e.g. `family_killed`, `tournament_announced`)
- `day` (int; world day when event is recorded)
- `created_at`
- `data` (JSON; event-specific payload)

Indexes:

- `(world_id, day)`
- `(world_id, type, day)`
- `(character_id, day)`

## Goal Catalog (YAML)

All goal definitions live in `config/game/goals.yaml` (data-driven catalog).

### Life goals

`life_goals.<life_goal_code>` defines:

- `label`
- `current_goal_pool`: list of `{ code, weight }`

Life goals are expanded by adding new YAML entries. Current goals can be referenced by multiple life goals.

### Current goals

`current_goals.<current_goal_code>` defines:

- `label`
- `interruptible` (bool) — whether world/character events may override/clear it while in progress
- `defaults` (optional map) — merged into `current_goal_data` on selection
- `handler` (string) — PHP handler service id/class name that implements behavior

### NPC life-goal assignment at creation

NPCs pick a starting `life_goal_code` randomly (true randomness) from an archetype-weighted list:

- `npc_life_goals.<npc_archetype>`: list of `{ code, weight }`

The selected `life_goal_code` is persisted to `game_character_goal` at NPC creation time.

Player characters must select `life_goal_code` at creation time and may choose from the full catalog.

## Daily Simulation Integration

### Resolution timing

Apply events and (re)select goals at the **start of each simulated day** (per character), before training/travel is
applied.

### Resolver rules (per character/day)

1) Skip if `last_resolved_day === world.currentDay`.
2) Load unprocessed events for the world and character:
    - `character_id = <me>` OR `character_id IS NULL`
    - Filter broadcast events by radius (Manhattan distance) if `data` contains a `{center_x, center_y, radius}`.
3) Consume events in id order; update `last_processed_event_id` through all consumed events.
4) Life-goal change is limited to **at most one per day**:
    - After the first life-goal change triggers, further events may still set/clear current goals (if allowed), but they
      cannot trigger additional life-goal changes.
5) Compatibility:
    - If `current_goal_code` is not in the `life_goal_code`’s `current_goal_pool`, clear it (and JSON) and set
      `current_goal_complete=false`.
6) “Needs new current goal” if:
    - `current_goal_code IS NULL` OR `current_goal_complete=true` OR incompatible (cleared above).
    - Pick randomly by weight from `life_goal.current_goal_pool`, set `current_goal_complete=false`, and initialize JSON
      with `current_goals.<code>.defaults`.

### Events-driven life-goal changes (conditional)

In YAML:

`event_rules.<event_type>.from.<life_goal_code>` defines:

- `chance` (0..1)
- `transitions`: list of `{ to, weight }`

If an event triggers a life goal change:

- Update `life_goal_code` (persist immediately)
- Keep current goal only if it remains compatible with the new life goal; otherwise clear and repick

### Events-driven current-goal overrides (interruptibility + compatibility)

Events may also contain actions:

- `set_current_goal`: `{ code, data }`
- `clear_current_goal`: `true`

Rules:

- `set_current_goal` requires compatibility with the (possibly updated) `life_goal_code` pool.
- If the character currently has an incomplete goal:
    - Only allow set/clear if the current goal is `interruptible=true`.
- If there is no current goal, or it is complete, set/clear is always allowed.

## Goal Execution (PHP handlers)

Current goals drive daily behavior.

Each `current_goal_code` has a dedicated PHP handler (strategy pattern). Handlers may share helpers (e.g. “travel toward
target”), but each goal is its own unit of behavior.

Conceptual handler contract:

- Input: `(Character, World, current_goal_data, context)`
- Output:
    - `DailyPlan` (train/travel/rest + optional travel target)
    - updated `current_goal_data`
    - `completed` boolean (sets `current_goal_complete=true`)

`SimulationClock` executes the returned `DailyPlan` using existing mechanics (training multipliers via dojo tiles,
travel stepper, etc.), then persists goal state updates.

## Notes on Randomness and Testing

- “True randomness” is used for:
    - Picking current goals from a life goal pool
    - Event chance rolls and weighted transitions
- Stability is achieved by persisting the selected `life_goal_code`/`current_goal_code` + JSON state.
- Tests should avoid flaky randomness by using YAML configurations with `chance: 1.0` and deterministic weights for the
  scenario under test (or by injecting a test RNG if/when needed).

