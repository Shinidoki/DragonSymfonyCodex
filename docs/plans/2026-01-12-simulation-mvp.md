# Simulation MVP Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Build a minimal backend vertical slice for a persistent, simulated Dragon Ball–inspired RPG world: create a
world, create characters/NPCs, advance time, and apply deterministic training-based stat growth with tiered
player-facing output.

**Architecture:** Keep game rules in plain PHP domain/services (`src/Game/*`) and treat Symfony as the delivery
mechanism (CLI + optional JSON endpoints). Use Doctrine entities for persistence and small pure services/value objects
for simulation rules to keep logic testable.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

---

### Task 1: Create core domain types (race, time, tiers)

**Files:**

- Create: `src/Game/Domain/Race.php`
- Create: `src/Game/Domain/Time/Tick.php`
- Create: `src/Game/Domain/Time/Day.php`
- Create: `src/Game/Domain/Stats/Stat.php`
- Create: `src/Game/Domain/Stats/StatTier.php`
- Test: `tests/Game/Domain/Stats/StatTierTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/Stats/StatTierTest.php
namespace App\Tests\Game\Domain\Stats;

use App\Game\Domain\Stats\StatTier;
use PHPUnit\Framework\TestCase;

final class StatTierTest extends TestCase
{
    public function testMapsNumericStatToTierLabel(): void
    {
        self::assertSame('Weak', StatTier::fromValue(1)->label());
        self::assertSame('Average', StatTier::fromValue(10)->label());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Stats/StatTierTest.php`
Expected: FAIL (class not found).

**Step 3: Write minimal implementation**

```php
<?php
// src/Game/Domain/Stats/StatTier.php
namespace App\Game\Domain\Stats;

enum StatTier: int
{
    case Weak = 1;
    case Average = 2;
    case Trained = 3;
    case Strong = 4;
    case Exceptional = 5;
    case Elite = 6;
    case Legendary = 7;

    public static function fromValue(int $value): self
    {
        return match (true) {
            $value < 5 => self::Weak,
            $value < 15 => self::Average,
            $value < 30 => self::Trained,
            $value < 60 => self::Strong,
            $value < 120 => self::Exceptional,
            $value < 250 => self::Elite,
            default => self::Legendary,
        };
    }

    public function label(): string
    {
        return $this->name;
    }
}
```

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Stats/StatTierTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: add core stat tier mapping"
```

---

### Task 2: Model core attributes and deterministic growth rules

**Files:**

- Create: `src/Game/Domain/Stats/CoreAttributes.php`
- Create: `src/Game/Domain/Stats/Growth/TrainingIntensity.php`
- Create: `src/Game/Domain/Stats/Growth/TrainingResult.php`
- Create: `src/Game/Domain/Stats/Growth/TrainingGrowthService.php`
- Test: `tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php
namespace App\Tests\Game\Domain\Stats\Growth;

use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Stats\Growth\TrainingIntensity;
use PHPUnit\Framework\TestCase;

final class TrainingGrowthServiceTest extends TestCase
{
    public function testTrainingIncreasesRelevantStats(): void
    {
        $service = new TrainingGrowthService();
        $before = CoreAttributes::baseline();

        $after = $service->train($before, TrainingIntensity::Normal);

        self::assertGreaterThan($before->strength, $after->strength);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php`
Expected: FAIL (missing classes).

**Step 3: Write minimal implementation**

- Implement `CoreAttributes` as a simple immutable value object with public readonly ints.
- Implement `TrainingGrowthService::train()` with a small, deterministic increment (no RNG yet).
- Keep “soft cap” as a TODO (don’t overbuild).

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Stats/Growth/TrainingGrowthServiceTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: add deterministic training-based stat growth"
```

---

### Task 3: Add Doctrine entities for World and Character

**Files:**

- Create: `src/Entity/World.php`
- Create: `src/Entity/Character.php`
- Create: `src/Repository/WorldRepository.php`
- Create: `src/Repository/CharacterRepository.php`
- Modify: `config/packages/doctrine.yaml`
- Modify: `config/services.yaml`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine command)
- Test: `tests/Game/Integration/PersistenceSmokeTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/PersistenceSmokeTest.php
namespace App\Tests\Game\Integration;

use App\Entity\World;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceSmokeTest extends KernelTestCase
{
    public function testWorldEntityBootsAndIsMappable(): void
    {
        self::bootKernel();
        self::assertNotNull(new World('seed-1'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/PersistenceSmokeTest.php`
Expected: FAIL until entities exist.

**Step 3: Implement entities (minimal fields)**

- `World`: `id`, `seed`, `currentDay` (int), `createdAt`.
- `Character`: `id`, `name`, `race`, `ageDays` (int), `world` (ManyToOne), core attributes (either columns or
  embeddable).

**Step 4: Create and run migration**

Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

Expected: Migration runs cleanly against local Postgres.

**Step 5: Commit**

```bash
git add src/Entity src/Repository config migrations tests
git commit -m "feat: persist worlds and characters"
```

---

### Task 4: World creation service (seeded, repeatable)

**Files:**

- Create: `src/Game/Application/World/WorldFactory.php`
- Create: `src/Game/Application/World/CreateWorldHandler.php`
- Create: `src/Command/GameWorldCreateCommand.php`
- Test: `tests/Game/Application/World/CreateWorldHandlerTest.php`

**Step 1: Write the failing test**

- Assert `CreateWorldHandler` returns a persisted `World` with `currentDay = 0`.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/World/CreateWorldHandlerTest.php`

**Step 3: Implement handler + console command**

- `php bin/console game:world:create --seed=earth-0001`

**Step 4: Run command manually**
Expected: Prints created world id + seed.

**Step 5: Commit**

```bash
git add src/Game src/Command tests
git commit -m "feat: add world creation command"
```

---

### Task 5: Simulation clock (days and ticks) with background daily resolution

**Files:**

- Create: `src/Game/Application/Simulation/AdvanceDayHandler.php`
- Create: `src/Game/Domain/Simulation/SimulationClock.php`
- Create: `src/Command/GameSimAdvanceCommand.php`
- Test: `tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`

**Step 1: Write the failing test**

- Create world + character, advance 1 day, assert `World.currentDay` increments and character gets one training “tick”
  summary.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`

**Step 3: Implement minimal daily loop**

- For MVP: each day, every character trains once (placeholder schedule).
- Update character attributes using `TrainingGrowthService`.

**Step 4: Run the command**
Run: `php bin/console game:sim:advance --world=<id> --days=7`
Expected: Prints day progression and a short summary of stat tiers.

**Step 5: Commit**

```bash
git add src/Game src/Command tests
git commit -m "feat: advance simulation by days with training growth"
```

---

### Task 6: Derive “power level” as a computed value (hidden, but loggable)

**Files:**

- Create: `src/Game/Domain/Power/PowerLevelCalculator.php`
- Test: `tests/Game/Domain/Power/PowerLevelCalculatorTest.php`

**Step 1: Write the failing test**

- Given known attributes, assert a stable computed integer power level.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/Power/PowerLevelCalculatorTest.php`

**Step 3: Implement calculator**

- Keep formula simple and monotonic (e.g., weighted sum); document it in code docblock only if needed.

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: add derived power level calculator"
```

---

### Task 7: Transformation state (unlock deferred, activation + exhaustion only)

**Files:**

- Create: `src/Game/Domain/Transformations/Transformation.php`
- Create: `src/Game/Domain/Transformations/TransformationState.php`
- Create: `src/Game/Domain/Transformations/TransformationService.php`
- Test: `tests/Game/Domain/Transformations/TransformationServiceTest.php`

**Step 1: Write the failing test**

- Activating a transformation applies a temporary multiplier and schedules post-use exhaustion penalty.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/Transformations/TransformationServiceTest.php`

**Step 3: Implement minimal rules**

- Support one form (e.g., `SuperSaiyan`) as a placeholder.
- Implement “safe duration window” as N ticks and exhaustion as N days of penalty.

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: add basic transformation activation and exhaustion"
```

---

### Task 8: Add a thin JSON read API for inspection (optional UI foundation)

**Files:**

- Create: `src/Controller/Api/WorldController.php`
- Create: `src/Controller/Api/CharacterController.php`
- Test: `tests/Game/Http/WorldApiTest.php`

**Step 1: Write the failing HTTP test**

- Use `WebTestCase` to GET `/api/worlds/{id}` and assert JSON keys exist.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Http/WorldApiTest.php`

**Step 3: Implement controllers**

- Keep endpoints read-only for MVP:
    - `GET /api/worlds/{id}`
    - `GET /api/characters/{id}`

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Controller tests
git commit -m "feat: add read-only inspection API for world and characters"
```

---

### Task 9: Document local dev workflow (Docker + commands)

**Files:**

- Modify: `README.md` (or create it if missing)

**Step 1: Add minimal “Getting Started”**

- Postgres via `docker compose up -d`
- Configure `.env.local` DATABASE_URL
- Create world, create character, advance sim

**Step 2: Verify commands run locally**
Run: `php bin/console list | Select-String game:`

**Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add local dev and simulation commands"
```

---

## Out of Scope (Explicitly Deferred)

- Procedural map generation (tiles/local maps beyond placeholders).
- Full NPC goal system, memory/reputation spread, factions, travel/encounters.
- Unlock logic for transformations/techniques (events, mentors, near-death).
- Frontend UI/game loop; this plan targets backend foundation first.

