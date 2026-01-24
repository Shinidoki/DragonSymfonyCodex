# Long Actions Use NPC Profiles and Dojos Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Ensure long-action simulation advances NPCs using their archetypes (via `NpcProfile`) and dojo tiles, so
training/travel behavior matches normal world simulation.

**Architecture:** When `StartLongActionHandler` advances days for a local session, it already loads all world characters
and calls `SimulationClock::advanceDaysForLongAction`. Extend it to also load `NpcProfile`s for the world and dojo tile
coordinates, then pass them into the clock so the daily planner can make archetype- and dojo-aware decisions.

**Tech Stack:** Symfony, Doctrine ORM, PHPUnit.

### Task 1: Pass NPC profiles and dojo tiles into long-action simulation

**Files:**

- Modify: `src/Game/Application/Local/StartLongActionHandler.php`

**Step 1: Add queries for NPC profiles and dojo tiles**

- Load profiles for the world and build `array<int,NpcProfile>` keyed by character id (same strategy as
  `AdvanceDayHandler`).
- Load dojo tiles for the world (`hasDojo = true`) and convert to `list<TileCoord>`.

**Step 2: Pass data into the clock call**

- Extend the existing `advanceDaysForLongAction(...)` call to pass:
    - `npcProfilesByCharacterId: $profilesByCharacterId`
    - `dojoTiles: $dojoCoords`

**Step 3: Keep behavior unchanged for the player character**

- Player’s training multiplier remains controlled by `TrainingContext` (or `null` for non-training).

### Task 2: Add regression coverage for archetype/dojo-aware long actions

**Files:**

- Modify: `tests/Game/Application/Local/StartLongActionHandlerTest.php`

**Step 1: Add a test that proves fighters travel toward a dojo during a long action**

- Create a world + player + NPC, create a dojo `WorldMapTile`, and create an `NpcProfile` with `NpcArchetype::Fighter`
  for the NPC.
- Start a long action for the player (e.g. 1 day of training).
- Assert the NPC character moved one step toward the dojo and now has a travel target.

### Task 3: Verify

**Step 1: Run PHPUnit**

Run: `php bin/phpunit`
Expected: exit code 0.

