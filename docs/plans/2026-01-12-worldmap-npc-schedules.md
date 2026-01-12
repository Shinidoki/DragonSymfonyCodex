# World Map + NPC Daily Schedules Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add a minimal world map (planet → tiles) and daily NPC schedules (train/travel/rest) so the simulation can
move characters through space over days.

**Architecture:** Keep rules in `src/Game/*` as pure services/value objects; persist map state with Doctrine entities in
`src/Entity/*`. Extend the existing “advance by days” flow to apply a per-character daily plan (activity + optional
travel target) and keep it deterministic (seed + coordinates).

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

## Source of Truth (Docs)

- `docs/map_locations.md` (Universe/Planet/World Map Tile/Local Map; daily vs tick simulation; deferred interplanetary
  travel)
- `docs/npcs.md` (daily schedules + goals; active vs background simulation)
- `docs/time_simulation.md` (tick is one action; daily resolution for background)

---

### Task 1: Introduce world map primitives (coords, biome)

**Files:**

- Create: `src/Game/Domain/Map/TileCoord.php`
- Create: `src/Game/Domain/Map/Biome.php`
- Test: `tests/Game/Domain/Map/TileCoordTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/Map/TileCoordTest.php
namespace App\Tests\Game\Domain\Map;

use App\Game\Domain\Map\TileCoord;
use PHPUnit\Framework\TestCase;

final class TileCoordTest extends TestCase
{
    public function testRejectsNegativeCoordinates(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TileCoord(-1, 0);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Map/TileCoordTest.php`
Expected: FAIL (class not found).

**Step 3: Write minimal implementation**

```php
<?php
// src/Game/Domain/Map/TileCoord.php
namespace App\Game\Domain\Map;

final readonly class TileCoord
{
    public function __construct(public int $x, public int $y)
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Tile coordinates must be >= 0.');
        }
    }
}
```

Also add:

- `Biome` enum with a small set: `Plains`, `Forest`, `Mountains`, `Ocean`, `Desert`, `City`.

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Map/TileCoordTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Map tests/Game/Domain/Map
git commit -m "feat: add world map primitives"
```

---

### Task 2: Persist world map tiles and character position (background layer)

**Files:**

- Create: `src/Entity/WorldMapTile.php`
- Modify: `src/Entity/World.php`
- Modify: `src/Entity/Character.php`
- Create: `src/Repository/WorldMapTileRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Integration/WorldMapTileEntityTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/WorldMapTileEntityTest.php
namespace App\Tests\Game\Integration;

use App\Entity\World;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorldMapTileEntityTest extends KernelTestCase
{
    public function testWorldMapTileEntityIsLoadable(): void
    {
        self::bootKernel();
        self::assertNotNull(new World('seed-1'));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/WorldMapTileEntityTest.php`
Expected: FAIL until entity exists.

**Step 3: Implement entities (minimal fields)**

- `WorldMapTile`:
    - `id`
    - `world` (ManyToOne)
    - `x`, `y` (ints; unique constraint on `(world_id, x, y)`)
    - `biome` (string/enum)
    - `hasSettlement` (bool, optional)
- `World`:
    - Add `planetName` (string) (MVP: default `"Earth"`)
    - Add `width`, `height` (ints) so generation knows bounds
- `Character`:
    - Add current tile position: `tileX`, `tileY` (ints, default 0)
    - Add optional travel target: `targetTileX`, `targetTileY` (nullable ints)

**Step 4: Create and run migration**

Run:

- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

Expected: Migration runs cleanly and no reserved-word table names are introduced (avoid `tile`/`character` pitfalls; use
explicit `#[ORM\Table]` if needed).

**Step 5: Commit**

```bash
git add src/Entity src/Repository migrations tests
git commit -m "feat: persist world map tiles and character position"
```

---

### Task 3: Deterministic map generation service (seed + coords → biome)

**Files:**

- Create: `src/Game/Domain/Map/MapGenerator.php`
- Create: `src/Game/Application/Map/GenerateWorldMapHandler.php`
- Create: `src/Command/GameWorldGenerateMapCommand.php`
- Test: `tests/Game/Domain/Map/MapGeneratorTest.php`

**Step 1: Write the failing test**

```php
<?php
// tests/Game/Domain/Map/MapGeneratorTest.php
namespace App\Tests\Game\Domain\Map;

use App\Game\Domain\Map\Biome;
use App\Game\Domain\Map\MapGenerator;
use App\Game\Domain\Map\TileCoord;
use PHPUnit\Framework\TestCase;

final class MapGeneratorTest extends TestCase
{
    public function testIsDeterministicBySeedAndCoord(): void
    {
        $g = new MapGenerator();

        $a = $g->biomeFor('seed-1', new TileCoord(3, 7));
        $b = $g->biomeFor('seed-1', new TileCoord(3, 7));
        $c = $g->biomeFor('seed-2', new TileCoord(3, 7));

        self::assertSame($a, $b);
        self::assertNotSame($a, $c);
        self::assertInstanceOf(Biome::class, $a);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Map/MapGeneratorTest.php`
Expected: FAIL (class not found).

**Step 3: Implement minimal deterministic selection**

- Use a stable hash (e.g., `hash('xxh128', "$seed:$x:$y")` or `sha1`) and map ranges to `Biome` values.
- Keep it simple (no noise libs).

**Step 4: Run test to verify it passes**

Run: `php bin/phpunit tests/Game/Domain/Map/MapGeneratorTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: add deterministic world map generator"
```

---

### Task 4: Generate tiles for a world (create-or-update idempotently)

**Files:**

- Modify: `src/Repository/WorldMapTileRepository.php`
- Modify: `src/Game/Application/Map/GenerateWorldMapHandler.php`
- Test: `tests/Game/Application/Map/GenerateWorldMapHandlerTest.php`

**Step 1: Write the failing test**

- Given a world with `width=3,height=2`, handler creates exactly `6` tiles.
- Running twice does not create duplicates.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Application/Map/GenerateWorldMapHandlerTest.php`

**Step 3: Implement handler**

- For each `(x,y)` within bounds, create tile if missing, set biome from `MapGenerator`.
- Persist in batches (but keep simple; only optimize if needed).

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game src/Repository tests
git commit -m "feat: generate world map tiles for a world"
```

---

### Task 5: Daily schedule model (train / travel / rest)

**Files:**

- Create: `src/Game/Domain/Npc/DailyActivity.php`
- Create: `src/Game/Domain/Npc/DailyPlan.php`
- Create: `src/Game/Domain/Npc/DailyPlanner.php`
- Test: `tests/Game/Domain/Npc/DailyPlannerTest.php`

**Step 1: Write the failing test**

- If character has a travel target, plan returns `Travel`.
- Else default to `Train` (MVP).

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/Npc/DailyPlannerTest.php`

**Step 3: Implement planner**

- Pure, deterministic rules only (no RNG yet).

**Step 4: Run tests**
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Npc tests/Game/Domain/Npc
git commit -m "feat: add daily schedule planner"
```

---

### Task 6: Movement on world map (one tile per day toward target)

**Files:**

- Create: `src/Game/Domain/Map/Travel/StepTowardTarget.php`
- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Test: `tests/Game/Domain/Map/Travel/StepTowardTargetTest.php`

**Step 1: Write the failing test**

- From `(0,0)` toward `(2,0)` moves to `(1,0)`.
- From `(1,1)` toward `(1,1)` stays.

**Step 2: Run test to verify it fails**
Run: `php bin/phpunit tests/Game/Domain/Map/Travel/StepTowardTargetTest.php`

**Step 3: Implement step helper**

- Prefer Manhattan movement (x then y) for determinism.

**Step 4: Integrate into `SimulationClock`**

- Each day:
    - decide daily plan via `DailyPlanner`
    - if `Train`: apply `TrainingGrowthService`
    - if `Travel`: update `tileX/tileY` one step toward `targetTileX/targetTileY`
    - if reached target: clear travel target

**Step 5: Commit**

```bash
git add src/Game tests/Game
git commit -m "feat: move characters across world map during daily simulation"
```

---

### Task 7: CLI commands for map generation and travel intent

**Files:**

- Create: `src/Command/GameWorldGenerateMapCommand.php`
- Create: `src/Command/GameCharacterSetTravelCommand.php`
- Test: `tests/Game/Application/Simulation/AdvanceDayHandlerTest.php` (extend existing test)

**Step 1: Extend failing test**

- Create character with a travel target and advance 1 day → tile position changes.

**Step 2: Implement commands**

- `php bin/console game:world:generate-map --world=1 --width=32 --height=32 --planet=Earth`
- `php bin/console game:character:set-travel --character=1 --x=10 --y=10`

**Step 3: Run commands manually**
Expected:

- generate-map prints created tile count
- set-travel prints character id + new target

**Step 4: Run targeted tests**
Run: `php bin/phpunit tests/Game/Application/Simulation/AdvanceDayHandlerTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Command tests
git commit -m "feat: add CLI for world map generation and travel intent"
```

---

### Task 8: Read-only API for tiles and character location

**Files:**

- Create: `src/Controller/Api/WorldMapController.php`
- Modify: `src/Controller/Api/CharacterController.php`
- Test: `tests/Game/Http/WorldApiTest.php`

**Step 1: Write the failing test**

- `GET /api/worlds/{id}/tiles?x=0&y=0` returns a tile payload.
- `GET /api/characters/{id}` includes `tileX`, `tileY`, and travel target fields.

**Step 2: Implement controller**

- Add endpoint: `GET /api/worlds/{id}/tiles?x=&y=` for MVP (single tile query).

**Step 3: Run tests**
Run: `php bin/phpunit tests/Game/Http/WorldApiTest.php`
Expected: PASS.

**Step 4: Commit**

```bash
git add src/Controller tests
git commit -m "feat: expose world map tiles and character location via API"
```

---

### Task 9: Update docs to reflect world map + schedules

**Files:**

- Modify: `README.md`

**Step 1: Add new usage examples**

- Map generation
- Setting travel target
- Advancing simulation to move + train

**Step 2: Verify commands list**
Run: `php bin/console list | Select-String game:`
Expected: shows `game:world:generate-map` and `game:character:set-travel`.

**Step 3: Commit**

```bash
git add README.md
git commit -m "docs: add world map and NPC schedule usage"
```

---

## Explicitly Deferred (Stay YAGNI)

- Local map grid, tick-based exploration, and turn-based combat loop (`docs/time_simulation.md`).
- Encounters and transitions world-map ⇄ local-map (`docs/map_locations.md`).
- NPC memory/reputation spread, factions, and long-term goals (`docs/npcs.md`).
- Interplanetary travel (`docs/map_locations.md` calls it deferred).

