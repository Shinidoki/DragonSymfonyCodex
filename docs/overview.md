# Project Overview

**Procedural Dragon Ball Legacy RPG**

---

## 1. Project Vision

This project is a **procedurally generated role-playing game** inspired by the universe and rules of **Dragon Ball (Z / GT / Super)**.

Rather than retelling existing stories or focusing on canon protagonists, the game aims to simulate a **living Dragon Ball universe** where history unfolds dynamically through the actions of simulated characters and the player.

The player controls **one character at a time** within a persistent world that exists independently of the player’s actions. Characters are born, train, fight, age, form relationships, and die. Their actions leave lasting consequences that shape the world for future generations.

Death is not failure. The true progression of the game lies in **legacy, reputation, and world impact**.

---

## 2. Core Experience

The player begins as an original character in a newly generated world and pursues a life shaped by opportunity, danger, and ambition. Possible paths include:

* Becoming a powerful martial artist
* Participating in tournaments
* Defending cities or planets
* Serving or opposing galactic powers
* Seeking forbidden power
* Living a quiet life and raising a family

The game does not guarantee heroism or success. The player may rise to legend, fade into obscurity, or die young. Regardless of outcome, the world continues to evolve.

---

## 3. Living, Persistent World

Each world is generated with its own:

* Geography
* Factions
* Cultures
* Conflicts
* Historical background

Once created, the world **persists permanently**:

* Cities can be destroyed and never rebuilt
* Powerful figures may rule for decades or fall suddenly
* Wars may begin and end without player involvement

Non-player characters act autonomously, forming alliances, pursuing goals, and shaping history alongside the player.

---

## 4. Canon-Driven Universe Rules

The game adheres closely to the **internal logic and rules** of the Dragon Ball universe:

* Canon races, transformations, and techniques exist as part of the world
* Power growth follows established Dragon Ball principles
* Transformations and advanced abilities are rare, dangerous, and difficult to master
* Artificial enhancement (such as android modification) exists as an in-world process rather than a starting choice

The canon is treated as **a set of physical and metaphysical laws**, not as a predefined storyline.

---

## 5. Character-Focused Gameplay

The player controls a **single character** at any given time.

That character:

* Has a finite lifespan
* Can form relationships
* Can fall in love and produce offspring
* Can suffer permanent injuries or psychological scars
* Can die permanently

When a character dies, the player may continue in the same world by taking control of a successor, such as a child or trained disciple. Traits, reputation, and history carry forward.

---

## 6. Legacy and Generations

A central theme of the project is **inheritance**.

Legacies may take many forms:

* Bloodlines known for power or instability
* Martial arts schools founded by former players
* Famous techniques passed down through generations
* Long-standing feuds or alliances

The world remembers past characters through stories, records, and consequences rather than scripted narration.

---

## 7. Emergent Stories, Not Scripted Narratives

The game does not rely on predefined quests or fixed storylines.

Instead:

* Conflicts arise naturally from world simulation
* Opportunities appear and disappear over time
* Major events may occur without player involvement
* The player’s choices influence—but do not control—the world

Every playthrough produces a unique history.

---

## 8. Long-Term Project Goals

The project is designed to:

* Support deep simulation and replayability
* Emphasize player choice and consequence
* Allow expansion without invalidating existing worlds
* Separate world logic from presentation, enabling future frontends

The initial development focus is on **backend systems and APIs**, ensuring a solid foundation before introducing visual or interactive interfaces.

### Current simulation note

Settlement migration pressure is driven by simulation events. When NPC relocation pressure crosses threshold, the loop emits `settlement_migration_committed`; goal resolution can then switch affected characters to `goal.find_job` with explicit destination coordinates (`target_x`, `target_y`).

---

## 9. Summary

This project aims to answer a single question:

> **What would happen if the Dragon Ball universe truly lived and evolved on its own—and the player was just one life within it?**

The result is a sandbox RPG where power is earned, history is permanent, and legacy matters more than victory.
