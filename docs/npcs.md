# NPCs & World Simulation

**Design Document**

---

## 1. Purpose

This document defines how **non-player characters (NPCs)** function within the game world:
how they live, grow, remember, interact, and shape the world independently of the player.

NPCs are not decorative or quest dispensers.
They are **autonomous agents** who exist within the same ruleset as the player and collectively form the living world.

---

## 2. Core Design Principles

1. **NPCs are simulated characters, not scripted actors**
2. **NPCs follow the same growth rules as the player**
3. **NPCs have lives that continue without player involvement**
4. **NPC perception and memory are imperfect**
5. **NPCs can meaningfully affect the world**
6. **The player is not the center of the simulation**

---

## 3. NPC Simulation Scope & Density

To balance depth and scale, NPC simulation operates at **multiple levels of granularity**.

### 3.1 Active Simulation (Near the Player)

NPCs that are:

* Near the player
* Visible to the player
* Directly interacting with the player

are simulated:

* Continuously
* Tick by tick
* With full stat, behavior, and decision updates

This allows:

* Responsive combat
* Organic conversations
* Reactive world behavior

---

### 3.2 Background Simulation (Far from the Player)

NPCs outside the player’s immediate vicinity are:

* Simulated on a **daily basis**
* Resolved in larger time steps

At the end of each day:

* NPC schedules are evaluated
* Tasks are resolved
* Training, travel, conflicts, and events are processed

This ensures:

* The world progresses even when unseen
* NPCs continue to live meaningful lives
* Performance remains manageable

---

## 4. NPC Life & Daily Schedules

Every NPC has:

* A background (farmer, fighter, merchant, scientist, etc.)
* A daily routine
* Needs (rest, safety, purpose)

Examples:

* Farmers work fields and avoid danger
* Fighters train, travel, or seek conflict
* Criminals plan and execute illegal activities
* Guardians patrol or respond to threats

NPCs may deviate from routines when:

* Threatened
* Injured
* Motivated by strong goals
* Influenced by world events

---

## 5. NPC Growth & Power

NPCs:

* Train
* Learn techniques
* Unlock transformations
* Age
* Plateau or break limits

Key rule:

> **NPCs grow under the same rules as the player and may become equally strong or stronger.**

There is no artificial cap placed on NPC potential.

---

## 6. Morality, Alignment & Behavior

NPC behavior is guided by:

* Personality traits
* Background
* Cultural values
* Moral tendencies (not binary alignment)

### 6.1 Strong NPC Behavior

Powerful NPCs do not remain static.

They may:

* Roam the world
* Intervene in conflicts
* Protect regions
* Cause destruction
* Hunt other powerful beings

Good and evil NPCs may collide without player involvement, creating off-screen history.

---

## 7. NPC Perception & Memory

NPCs do not have perfect memory or awareness.

They may remember different aspects of the player **independently**:

* Face
* Name
* Aura
* Reputation

Recognition improves through:

* Repeated encounters
* Strong impressions
* Famous deeds
* Unique aura presence

Example:

* An NPC may not recognize the player visually
* But may recognize their aura if capable of sensing it
* Or associate them with rumors rather than personal experience

Memory is contextual, partial, and fallible.

---

## 8. Reputation & Information Spread

NPC knowledge spreads through:

* Direct encounters
* Witnesses
* Rumors
* Factions

Reputation is:

* Local before it is global
* Sometimes inaccurate
* Influenced by perspective

An NPC’s opinion of the player may differ greatly from another’s.

---

## 9. Allies & Companions

The player may gain NPC allies who:

* Follow and assist in combat
* Act autonomously
* Make their own tactical decisions

Key rules:

* Allies are not directly controlled
* Allies do not abandon the player permanently
* Defeated allies may retreat to recover

Recovered allies may later be encountered again in the world.

Allies retain:

* Their own goals
* Their own growth
* Their own perception of the player

---

## 10. Rivals & Nemesis Arcs

NPCs can become rivals through repeated conflict.

After defeat, a rival may:

* Retreat
* Train
* Change strategy
* Return stronger

Rivalry is:

* Emergent
* Persistent
* Personal

Rival relationships may evolve into:

* Hostile nemesis arcs
* Mutual respect
* Friendly rivalry

Alignment changes do **not** automatically end rivalry.

Rivalries may end when:

* One party dies
* One vastly surpasses the other
* Motivation is lost
* Circumstances fundamentally change

---

## 11. NPC Failure & Mortality

NPCs are allowed to fail.

They may:

* Lose battles
* Die attempting heroic acts
* Be defeated by other NPCs
* Abandon goals after repeated failure

The world does not wait for the player to resolve conflicts.

---

## 12. Player–NPC Parity

The player is subject to the same world rules as NPCs.

* NPCs can surpass the player
* NPCs can unlock rare powers
* NPCs can become legends independently

The difference between player and NPC lies in **agency**, not privilege.

---

## 13. Summary

* NPCs are autonomous agents with real lives
* Simulation detail adapts to player proximity
* NPCs grow, remember, and act independently
* Allies, rivals, and nemeses emerge naturally
* The world evolves with or without the player

NPCs are the **engine of history**, not background noise.
