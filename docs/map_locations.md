# World Map & Combat Location Model

**Design Document (Cutover-aligned)**

---

## 1. Purpose

This document defines how space is modeled after the hard local-zone cutover.

The model must:

- Keep a large persistent world
- Support travel and settlement-scale simulation
- Trigger turn-based RPG fights when conflicts happen
- Avoid reintroducing local tactical map runtime concepts

---

## 2. Spatial Hierarchy

The game uses three persistent spatial layers plus one ephemeral combat context:

1. **Universe**
2. **Planet**
3. **World Map Tile**
4. **Fight Encounter (ephemeral, combat-only)**

There is **no separate encounter-space/local-zone map** in runtime.

---

## 3. Universe Layer

The Universe is a top-level container for one or more planets and their long-term history.

It coordinates cross-planet structure but does not run per-action tactical simulation.

---

## 4. Planet Layer

A Planet is a world container with:

- geography
- populations
- settlements/factions
- ongoing simulation history

Initial implementation focuses on one primary planet, but the model remains multi-planet compatible.

---

## 5. World Map Layer

### 5.1 World map overview

Each planet has a tile-based world map used for:

- travel
- settlement placement
- background simulation context
- conflict/event staging

Tiles represent large regions, not tactical grids.

### 5.2 Tile properties

A tile may include:

- terrain/biome tags
- settlement presence
- infrastructure markers (e.g., dojo)
- threat/activity context

### 5.3 Movement model

Movement across tiles consumes macro time and is resolved by simulation flow.

No cell-by-cell player navigation layer exists in this architecture.

---

## 6. Fight Encounter Context (Combat-only)

When conflict resolves into combat, the system opens an **ephemeral fight encounter context**.

This context is:

- turn-based
- speed/initiative driven
- focused on combat resolution only
- not a persistent tactical map layer

After combat resolves, simulation continues on the world map timeline.

---

## 7. Targeting Model (Basic)

Combat targeting is intentionally minimal:

- **single-target**: one enemy
- **aoe**: all enemies in the current fight

Non-goals in this architecture:

- directional targeting
- point/cell targeting
- line/ray/path targeting on a local grid

---

## 8. Simulation Fidelity

### 8.1 World simulation

Outside active fights, characters are simulated through world/day resolution loops, goals, and events.

### 8.2 Fight simulation

Inside active fights, actions resolve in turn order with one action per turn step and speed-based frequency effects.

---

## 9. Persistence

Persistent world state remains map/event/entity-driven:

- character progression and outcomes
- settlement/economy changes
- tournament/combat result events

Fight encounter context itself is ephemeral and does not represent a persistent local map state.

---

## 10. Summary

- Persistent space = Universe → Planet → World Map Tiles
- Combat runs in a turn-based RPG encounter context
- Targeting is basic only (single-target + AoE)
- No encounter-space/local-zone runtime model remains
