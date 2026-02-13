# World Map & Location Model

**Design Document**

---

## 1. Purpose

This document defines how **space and location** are structured in the game universe and how they interact with time, simulation fidelity, NPC behavior, travel, encounters, and combat.

The model must:

* Support a **large, living world**
* Scale from **local tactical play** to **planetary exploration**
* Integrate cleanly with the time & simulation system
* Remain expandable to **multiple planets** without redesign

The design is inspired by the spatial logic of the **Dragon Ball**, where worlds are vast, travel matters, and major events can occur far from the protagonist.

---

## 2. Top-Level Spatial Hierarchy

The universe is structured into **three spatial layers**, ordered from largest to smallest:

1. **Universe**
2. **Planet**
3. **World Map Tile**
4. **Encounter Space**

Only the lowest layer (encounter space) operates at full tick-level detail.

---

## 3. Universe Layer

### 3.1 Definition

The **Universe** is the top-level container.

It contains:

* One or more planets
* Global history and events
* Cross-planet factions and legends

The universe itself does not simulate spatial detail; it only coordinates planets.

---

## 4. Planet Layer

### 4.1 Planet as a World Container

Each **planet** is a self-contained world with:

* Its own geography
* NPC population
* Cultures and factions
* Conflicts and history

The initial implementation focuses on **one primary planet**, but the design assumes more will be added later.

---

### 4.2 Planet Properties

A planet may define:

* Gravity rules
* Atmospheric constraints
* Travel limitations
* Environmental hazards

These properties influence:

* Movement speed
* Combat behavior
* Training efficiency
* Survival requirements

---

### 4.3 Interplanetary Travel (Deferred Feature)

Interplanetary travel:

* Is explicit and intentional
* Requires technology, abilities, or special locations
* Consumes significant time
* Is rare compared to planetary travel

NPCs and the player cannot accidentally move between planets.

---

## 5. World Map Layer (Per Planet)

### 5.1 World Map Overview

Each planet contains a **World Map**.

The world map:

* Is divided into **tiles**
* Represents large-scale geography
* Handles exploration, travel, and encounters

World map tiles may represent:

* Regions
* Biomes
* City zones
* Large wilderness areas

Tile size is **variable** and content-driven.

---

### 5.2 World Map Tile Properties

Each tile may include:

* Terrain type
* Points of interest
* Settlements
* Roads or travel routes
* Threat level

A tile does **not** simulate per-tick actions.

---

### 5.3 World Map Movement & Time

Movement on the world map:

* Consumes **time**, not ticks
* Time cost depends on:

    * Distance
    * Terrain
    * Mode of travel (walking, vehicle, flying, etc.)

Travel may:

* Trigger encounters
* Advance background simulation
* Cause world events to resolve

The player does not act tick-by-tick on the world map.

---

## 6. Encounter Space Layer

### 6.1 Encounter Space Overview

Entering a world map tile transitions the player into a **encounter space**.

A encounter space:

* Is grid-based (cells)
* Represents concrete, explorable space
* Is where all tick-based simulation occurs

Encounter spaces may represent:

* City streets
* Villages
* Interiors
* Wilderness sections
* Battlefields

---

### 6.2 Encounter Space Size & Variability

Encounter space size:

* Is variable
* Depends on tile type and context
* Is designed for tactical play

Encounter spaces are intentionally limited in scope to support:

* Turn-based combat
* NPC interaction
* Clear spatial reasoning

---

## 7. Simulation Zones & Fidelity

### 7.1 Active Encounter

The **Active Encounter** is the current encounter space.

Entities in the Active Encounter:

* Are simulated **per tick**
* Act using the one-action-per-tick rule
* Can observe and react to each other directly

This includes:

* Player
* Nearby NPCs
* Environmental objects

---

### 7.2 Background Zone

Entities outside the Active Encounter:

* Are simulated in **daily resolution**
* Follow schedules and long-term goals
* Continue to train, travel, and act

This applies:

* Across the same planet
* Across other planets

---

## 8. Transitions Between Layers

### 8.1 World Map → Encounter Space

When entering a tile:

* Time stops advancing in world-map units
* The encounter space is instantiated
* Relevant NPCs are loaded into the Active Encounter

NPCs are fast-forwarded to the current time before activation.

---

### 8.2 Encounter Space → World Map

When leaving an encounter space:

* NPCs are summarized into:

    * Current intent
    * Position
    * Condition
* Background simulation resumes

No NPC is frozen or reset.

---

## 9. Encounters

### 9.1 Encounter Sources

Encounters may occur:

* During world map travel
* Upon entering a tile
* While exploring an encounter space
* Due to NPC goals (hunting, patrols, ambushes)

---

### 9.2 Encounter Resolution

Encounters:

* Transition to an encounter space if combat or interaction occurs
* May be avoided, escalated, or resolved socially
* Are influenced by:

    * Power perception
    * Reputation
    * Awareness

Combat always occurs on an encounter space.

---

## 10. Combat & Space Interaction

* Combat is turn-based
* Each action consumes one tick
* Speed differences may allow multiple actions
* Terrain and positioning matter
* Combat does not advance world-map time

Once combat ends, normal simulation resumes.

---

## 11. NPC Interaction with the Map

NPCs:

* Travel across world map tiles
* Enter and leave encounter spaces
* Act based on goals and schedules
* Cause changes to tiles (damage, destruction, protection)

Strong NPCs may:

* Roam across regions
* Influence multiple tiles
* Travel between planets (later)

---

## 12. Persistence & History

All spatial changes are persistent:

* Destroyed locations remain destroyed
* NPC deaths are permanent
* Environmental changes are recorded as events

The map becomes a **historical artifact** over time.

---

## 13. Design Goals Recap

This model ensures:

* Large worlds without per-tick global simulation
* Meaningful travel and distance
* Tactical, readable combat
* NPC autonomy at scale
* Easy expansion to new planets

---

## 14. Explicit Non-Goals (For Now)

This document does not define:

* Procedural terrain algorithms
* Exact tile or map dimensions
* Rendering or UI
* Economy or resource systems

These belong to later layers.

---

## 15. Summary

* The universe contains multiple planets
* Each planet has its own world map
* World maps handle scale and time
* Encounter spaces handle detail and actions
* Simulation fidelity increases with proximity
* The system is expandable without redesign

> **Scale lives on the world map; stories happen on the encounter space.**
