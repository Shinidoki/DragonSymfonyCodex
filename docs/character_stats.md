# Character Stats & Progression

**Design Document**

---

## 1. Purpose

This document defines how **character stats** function in the game:
what they represent, how they grow, how they are perceived by the player, and how progression is communicated through gameplay and world simulation.

Stats exist to:

* Enable long-term character growth
* Support power level derivation
* Provide clear and satisfying feedback to the player
* Preserve immersion by avoiding excessive numerical exposure

The system follows the internal logic of the **Dragon Ball**, where growth is uneven, contextual, and often sudden under extreme circumstances.

---

## 2. Core Design Principles

1. **Stats grow through action, not abstraction**
   Training, combat, survival, and experience drive growth.

2. **Numbers exist internally, tiers exist externally**
   The game uses numeric values internally, but the player interacts with tier-based descriptions.

3. **Progress should be felt before it is measured**
   Mechanical and narrative feedback precede explicit indicators.

4. **Rapid growth is rare and meaningful**
   Sudden stat increases only occur under exceptional circumstances.

5. **NPCs participate in progression feedback**
   The world acknowledges and reacts to character growth.

---

## 3. Structure of Character Stats

Character stats are organized into **three conceptual layers**.

---

## 4. Layer 1: Core Attributes (Trainable Stats)

Core attributes are **numeric values** that increase through training and experience.
They represent the character’s actual development and are the foundation of all derived values.

### 4.1 Attribute Categories

#### Physical Attributes

* Strength
* Speed
* Endurance
* Durability

#### Ki Attributes

* Ki Capacity
* Ki Control
* Ki Recovery

#### Mental Attributes

* Focus
* Discipline
* Adaptability

These attributes:

* Increase through relevant activities
* Are affected by race, traits, age, and injuries
* Persist across the character’s life unless permanently damaged

---

## 5. Layer 2: Derived Stats (Hidden Values)

Derived stats are **calculated**, not trained directly.

Examples include:

* Power Level
* Combat effectiveness
* Technique efficiency
* Transformation stability
* Recovery rates

Derived stats:

* Change dynamically
* Depend on current condition and state
* Are usually not shown directly to the player

They ensure mechanical depth without overwhelming the interface.

---

## 6. Layer 3: Traits & Modifiers

Traits are **qualitative modifiers**, not primary stats.

Traits influence:

* Growth speed
* Breakthrough likelihood
* Risk tolerance
* Consistency of performance

Examples:

* Battle-hungry
* Disciplined
* Unstable
* Resilient
* Cautious

Traits explain why characters with similar stats can behave and grow very differently.

---

## 7. Stat Visibility: Tier-Based Presentation

### 7.1 Internal vs Player-Facing Representation

* Internally, all core attributes use numeric values
* Externally, the player sees **tiers**, not numbers

Example tier scale (illustrative):

* Weak
* Average
* Trained
* Strong
* Exceptional
* Elite
* Legendary

Each stat has its own tier progression.

---

### 7.2 Purpose of Tiers

Tiers provide:

* Clear progression signals
* Directional understanding
* Reduced incentive for min-maxing
* Strong immersion

The player understands:

* *That* they improved
* *Roughly how much* they improved
* *Where they stand*, without exact optimization

---

## 8. Stat Growth

### 8.1 Normal Growth

Normal growth is:

* Gradual
* Reliable
* Context-sensitive

Growth depends on:

* Type of training
* Frequency
* Intensity
* Character traits
* Current proximity to soft caps

Safe or repetitive training produces diminishing returns.

---

### 8.2 Exceptional Growth (Stat Jumps)

Stats may increase rapidly only under **special circumstances**, such as:

* Near-death survival
* Emotional breakthroughs
* Unlocking a transformation
* Extreme environments
* Rituals or rare interventions

These moments represent:

> “A fundamental shift in the character.”

They are rare, impactful, and narratively significant.

---

## 9. Feedback Systems for Progression

Progression feedback is delivered through **multiple overlapping channels**.

---

### 9.1 Direct Feedback

After training or combat, the player may receive messages such as:

* “Your movements feel more fluid.”
* “You can maintain your Ki more easily.”
* “Your body feels sturdier than before.”

These messages correspond to real stat increases.

---

### 9.2 Mechanical Feedback

As stats improve, the player notices:

* Reduced stamina drain
* Longer-lasting transformations
* Faster recovery
* Improved technique reliability

Challenges that were once difficult become manageable.

---

### 9.3 NPC Commentary & Memory

NPCs can remember:

* The last observed strength tier of the player
* How that strength was perceived (visual, aura sensing, technology)

When encountering the player again:

* NPCs may comment on improvement
* Shock or fear may occur after large jumps
* Suspicion may arise if the player appears weaker or suppressed

This reinforces progression socially and narratively.

---

## 10. Comparison & Simplicity (Initial Scope)

For the initial implementation:

* Tiers are absolute and self-contained
* No complex comparison system is required

Future expansions may include:

* Relative tiers (“stronger than most fighters you’ve met”)
* Mentor-based evaluation
* Social or faction-based comparison

The initial design intentionally favors clarity over complexity.

---

## 11. Summary

* Characters grow through trainable core attributes
* Stats increase gradually, with rare meaningful jumps
* Players see tiers instead of numbers
* Progression is reinforced through mechanics, narration, and NPC reactions
* Growth is contextual, immersive, and persistent

Stats exist to **support the fantasy of becoming stronger**, not to turn the game into a numbers exercise.
