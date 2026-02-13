# Time & Simulation Resolution

**Design Document (Cutover-aligned)**

---

## 1. Purpose

Defines how time advances and how simulation switches between macro world progression and turn-based combat.

Goals:

- large persistent world progression
- deterministic turn-based combat
- no local-zone/encounter-space runtime assumptions

---

## 2. Core Time Units

### 2.1 Turn step (combat action unit)

In combat, each meaningful action consumes one turn step.

Examples:

- basic attack
- technique use
- transformation action
- wait/prepare

This is an abstract action unit, not real-time seconds.

### 2.2 Day (macro simulation unit)

Outside active fights, world simulation advances in daily resolution:

- goals
- travel progression
- economy/settlement updates
- event loops

---

## 3. Runtime Simulation Modes

### 3.1 World mode (macro)

World map and population systems progress through day-scale simulation.

This is the default mode.

### 3.2 Fight mode (micro combat)

When combat is triggered, the system resolves a turn-based fight encounter.

Fight mode is:

- temporary
- combat-only
- initiative/speed sensitive
- independent from any local tactical grid layer

---

## 4. Combat Time Model

### 4.1 Turn-based with speed influence

Combat is strictly turn-based.

- faster combatants can act more frequently via initiative/turn scheduling
- each action still costs one turn step

### 4.2 Targeting scope

Targeting is intentionally basic:

- single-target
- AoE (all enemies in current fight)

Directional or point-on-grid targeting is not part of this model.

---

## 5. Transitions

### 5.1 World -> Fight

Conflict/event conditions trigger a fight encounter.

The fight resolves in turn steps, emits combat result events, and returns control to world simulation.

### 5.2 Fight -> World

After resolution, world/day simulation continues with updated state (health/outcomes/goals/events).

There is no persistent local-map state to serialize.

---

## 6. Consistency Rules

- One combat action = one turn step
- Same combat rules for NPCs and player-controlled entities
- Macro world simulation remains event/state consistent after fights
- No local-zone runtime side channel

---

## 7. Summary

- Macro time: day-based world simulation
- Micro time: turn-based combat steps
- Combat is RPG-style turn order with basic targeting only
- Architecture intentionally excludes encounter-space/local-zone runtime behavior
