# Time & Simulation Resolution

**Design Document (Finalized)**

---

## 1. Purpose

This document defines how **time progresses**, how **actions are resolved**, and how **simulation fidelity scales** based on player proximity.

The system must support:

* A large persistent world
* A detailed local simulation near the player
* Turn-based combat with speed-based initiative
* Consistent progression for both player and NPCs

---

## 2. Core Time Units

### 2.1 Tick (Action-Based)

A **tick represents exactly one action**.

Examples of actions:

* Move to an adjacent cell
* Perform an attack
* Use a technique
* Interact with an object or NPC
* Begin or end a transformation
* Observe / wait / prepare

Key rule:

> **Every meaningful action consumes exactly one tick.**

Ticks are **not seconds**.
They are abstract action units.

This allows:

* Turn-based combat
* Deterministic simulation
* Speed-based action advantage

---

### 2.2 Day (Macro Resolution)

A **day** is used for:

* Background NPC simulation
* Daily schedules and task resolution
* Off-screen travel and conflict
* Long-term growth and recovery

Days are resolved in batches when the player is not directly involved.

---

## 3. World Structure & Spatial Layers

The game world is divided into **two spatial layers**.

---

## 4. World Map (Macro Layer)

The **World Map** consists of **tiles**.

### 4.1 World Map Tiles

* Tile size is variable (biomes, regions, continents)
* Each tile represents a large area
* Tiles can contain:

    * Cities
    * Villages
    * Wilderness
    * Special locations

### 4.2 World Map Movement & Time Cost

Moving between world map tiles:

* Consumes **time**, not ticks
* Time cost depends on:

    * Distance
    * Mode of travel

Examples:

* Walking → slow
* Vehicle → medium
* Flying → fast
* Teleportation → near-instant (rare)

Travel time may span:

* Hours
* Days
* Multiple daily resolutions

During world map travel:

* Background NPC simulation continues
* World events may occur
* Encounters may be generated

---

## 5. Local Map (Micro Layer)

Each world map tile can be entered as a **local map**.

### 5.1 Local Map Properties

* Grid-based (cells)
* Size may vary per tile
* Represents streets, fields, interiors, battlefields

### 5.2 Local Map Time Rules

Inside a local map:

* Actions are resolved via **ticks**
* Movement is cell-by-cell
* NPCs near the player are fully simulated per tick

The local map is where:

* Exploration happens
* Conversations occur
* Combat is initiated and resolved

---

## 6. Simulation Zones

### 6.1 Active Zone (Tick Simulation)

The **Active Zone** includes:

* The current local map
* All entities visible or directly interacting with the player

Rules:

* Every entity acts via ticks
* Full stat, condition, and AI evaluation
* Immediate reactions possible

---

### 6.2 Background Zone (Daily Simulation)

Entities outside the Active Zone:

* Are simulated in **daily steps**
* Resolve schedules and goals once per day

This includes:

* NPC training
* Travel
* Conflicts
* World changes

---

## 7. Transitions Between Simulation Scales

### 7.1 From Background → Active

When the player enters a tile or an NPC approaches:

* The NPC is fast-forwarded to the current time
* Their daily intent is converted into a concrete local state

    * Position
    * Activity
    * Condition

No teleportation or reset occurs.

---

### 7.2 From Active → Background

When the player leaves:

* NPCs are summarized into:

    * Current goal
    * Travel direction
    * Training/rest state
* Future actions resume during daily resolution

---

## 8. Combat Time Model

### 8.1 Turn-Based Combat

Combat is **turn-based**, but **speed-sensitive**.

* Each combat action consumes one tick
* Turn order is determined by:

    * Speed stat
    * Current condition
    * Technique modifiers

---

### 8.2 Speed Advantage Rule

If the speed difference is sufficiently high:

* Faster fighters may act multiple times
* Slower fighters may lose reaction opportunities

Example:

* A very fast character may:

    * Attack twice
    * Move + attack
      before a slow opponent can act once

This preserves Dragon Ball–style combat:

> Speed can overwhelm power if the gap is large enough.

---

### 8.3 Combat vs World Time

While in combat:

* World time outside the local map is effectively paused
* Only the Active Zone advances via ticks

Once combat ends:

* Normal simulation resumes

---

## 9. Player Time Control

### 9.1 Normal Play

* Player actions consume ticks
* NPCs respond per tick

### 9.2 Long Actions / Fast Forward

Player may choose actions like:

* Sleep
* Train for hours
* Long-distance travel

These:

* Advance time directly
* Trigger daily resolution as needed
* Skip unnecessary ticks

---

## 10. Consistency Rules

The simulation must obey:

* **One action = one tick**
* **Same rules for player and NPCs**
* **No free actions**
* **Speed affects frequency, not cost**
* **Local precision does not change global outcomes**

---

## 11. What This Enables

This model supports:

* Deterministic turn-based combat
* Large-scale persistent worlds
* Speed-based dominance without real-time chaos
* Clear AI reasoning
* Scalable NPC simulation
* Future multiplayer or replay analysis (if desired)

---

## 12. Summary

* A tick represents a single action
* World map movement consumes time based on travel method
* Local maps operate on action-based ticks
* Combat is turn-based with speed-based multiple actions
* NPCs are simulated at high or low fidelity depending on proximity
* Time advances consistently and fairly across all systems
