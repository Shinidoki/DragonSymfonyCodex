# Data-Driven Techniques (DB + JSON Import) Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Replace hard-coded techniques with data-driven technique definitions stored in the database, updatable via JSON import, and executable by type (blast/beam/charged) with per-character proficiency affecting Ki cost, damage, and failure chance.

**Architecture:** Persist global technique behavior in `TechniqueDefinition` (type + JSON config) and per-character ownership/proficiency in `CharacterTechnique`. Runtime execution loads the latest `TechniqueDefinition` by code and applies a `TechniqueExecutor` strategy by `TechniqueType`, using only data from the definition config + character proficiency. Local mode calls techniques by string `techniqueCode` (no enums).

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

---

### Task 1: Add `TechniqueDefinition` persistence (DB source of truth)

**Files:**
- Create: `src/Game/Domain/Techniques/TechniqueType.php`
- Create: `src/Entity/TechniqueDefinition.php`
- Create: `src/Repository/TechniqueDefinitionRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Integration/TechniqueDefinitionEntityTest.php`

**Step 1: Write the failing integration test**

```php
<?php
// tests/Game/Integration/TechniqueDefinitionEntityTest.php
namespace App\Tests\Game\Integration;

use App\Entity\TechniqueDefinition;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TechniqueDefinitionEntityTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTechniqueDefinitionPersistsConfig(): void
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $t = new TechniqueDefinition(
            code: 'ki_blast',
            name: 'Ki Blast',
            type: TechniqueType::Blast,
            config: ['kiCost' => 3, 'range' => 2],
            enabled: true,
            version: 1,
        );

        $em->persist($t);
        $em->flush();
        $em->clear();

        $reloaded = $em->getRepository(TechniqueDefinition::class)->findOneBy(['code' => 'ki_blast']);
        self::assertInstanceOf(TechniqueDefinition::class, $reloaded);
        self::assertSame(TechniqueType::Blast, $reloaded->getType());
        self::assertSame(3, $reloaded->getConfig()['kiCost']);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Integration/TechniqueDefinitionEntityTest.php`
Expected: FAIL (missing classes).

**Step 3: Implement minimal entity**

- `TechniqueType` enum values: `blast`, `beam`, `charged`.
- `TechniqueDefinition` fields:
  - `id`
  - `code` (unique string)
  - `name` (string)
  - `type` (enum `TechniqueType`)
  - `config` (JSON array)
  - `enabled` (bool)
  - `version` (int)
  - `updatedAt` (datetime immutable)

**Step 4: Add migration**

Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate --no-interaction`

Expected: migration succeeds on MariaDB.

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Repository src/Game/Domain/Techniques migrations tests/Game/Integration
git commit -m "feat: persist technique definitions"
```

---

### Task 2: Add per-character technique knowledge + proficiency

**Files:**
- Create: `src/Entity/CharacterTechnique.php`
- Create: `src/Repository/CharacterTechniqueRepository.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php`
- Test: `tests/Game/Integration/CharacterTechniqueEntityTest.php`

**Step 1: Write failing integration test**

- Persist a `Character`, a `TechniqueDefinition`, link them via `CharacterTechnique` with `proficiency=7`, flush, reload, assert.

**Step 2: Run test**
Run: `php bin/phpunit tests/Game/Integration/CharacterTechniqueEntityTest.php`
Expected: FAIL (missing classes).

**Step 3: Implement**

- `CharacterTechnique` fields:
  - `id`
  - `character` (ManyToOne `Character`, cascade delete)
  - `technique` (ManyToOne `TechniqueDefinition`, cascade delete)
  - `proficiency` (int 0..100)
  - `createdAt`, `updatedAt`
- Unique constraint `(character_id, technique_id)`.
- Method: `incrementProficiency(int $by = 1): void` (caps at 100).

**Step 4: Migrate**
Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate --no-interaction`

**Step 5: Re-run test**
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Repository migrations tests/Game/Integration
git commit -m "feat: persist character technique proficiency"
```

---

### Task 3: JSON importer + CLI to upsert technique definitions

**Files:**
- Create: `src/Game/Application/Techniques/TechniqueImportService.php`
- Create: `src/Command/GameTechniquesImportCommand.php`
- Test: `tests/Game/Application/Techniques/TechniqueImportServiceTest.php`
- Add: `docs/data/techniques/ki_blast.json`
- Add: `docs/data/techniques/kamehameha.json`

**Step 1: Write failing test**

- Given a JSON file with `code/type/name/config/version/enabled`, importer upserts a `TechniqueDefinition` row by `code`.

**Step 2: Run test**
Run: `php bin/phpunit tests/Game/Application/Techniques/TechniqueImportServiceTest.php`
Expected: FAIL (missing service).

**Step 3: Implement importer**

- Accept `--path` file or directory.
- Parse JSON objects with schema:
  - `code` (string), `name` (string), `type` (`blast|beam|charged`), `config` (object), `enabled` (bool, default true), `version` (int, default 1)
- Upsert by `code`:
  - if existing: update name/type/config/enabled/version/updatedAt
  - else: insert new

**Step 4: Implement CLI**

- `php bin/console game:techniques:import --path=docs/data/techniques`

Expected: prints count imported and updated.

**Step 5: Commit**

```bash
git add src/Game/Application/Techniques src/Command tests docs/data/techniques
git commit -m "feat: import technique definitions from json"
```

---

### Task 4: Replace hard-coded technique API with `techniqueCode` strings

**Files:**
- Modify: `src/Game/Domain/LocalMap/LocalAction.php`
- Modify: `src/Command/GameLocalActionCommand.php`
- Modify: `tests/Game/Application/Local/LocalKiBlastFlowTest.php`
- Delete: `src/Game/Domain/Techniques/Technique.php`
- Delete: `src/Game/Domain/Techniques/TechniqueCatalog.php`
- Delete: `tests/Game/Domain/Techniques/TechniqueCatalogTest.php`

**Step 1: Write failing test update**

- Update `LocalKiBlastFlowTest` to call technique by code (`'ki_blast'`) and seed DB with `TechniqueDefinition` + `CharacterTechnique`.

**Step 2: Implement**

- `LocalAction` uses `?string $techniqueCode`.
- CLI uses `--technique=ki_blast` as string (no enum parsing).

**Step 3: Run test**
Run: `php bin/phpunit tests/Game/Application/Local/LocalKiBlastFlowTest.php`
Expected: PASS.

**Step 4: Commit**

```bash
git add src/Game/Domain/LocalMap src/Command tests
git rm src/Game/Domain/Techniques/Technique.php src/Game/Domain/Techniques/TechniqueCatalog.php tests/Game/Domain/Techniques/TechniqueCatalogTest.php
git commit -m "refactor: call techniques by code instead of enums"
```

---

### Task 5: Implement executor system (blast/beam/charged) + proficiency effects + partial Ki cost on failure

**Files:**
- Create: `src/Game/Domain/Techniques/Execution/TechniqueContext.php`
- Create: `src/Game/Domain/Techniques/Execution/TechniqueExecutor.php`
- Create: `src/Game/Domain/Techniques/Execution/BlastExecutor.php`
- Create: `src/Game/Domain/Techniques/Execution/BeamExecutor.php`
- Create: `src/Game/Domain/Techniques/Execution/ChargedExecutor.php`
- Create: `src/Game/Domain/Techniques/Execution/TechniqueMath.php`
- Create: `src/Game/Domain/Techniques/Execution/DeterministicRoller.php`
- Modify: `src/Game/Application/Local/Combat/CombatResolver.php`
- Modify: `src/Entity/LocalActor.php` (charging state)
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (charging columns)
- Test: `tests/Game/Application/Local/TechniqueFailureAndProficiencyTest.php`

**Step 1: Write failing test**

- Seed a `TechniqueDefinition` with:
  - `successChance` low at proficiency 0
  - `failureKiCostMultiplier` = `0.5`
- Seed `CharacterTechnique` with proficiency `0`.
- Use technique once and assert:
  - Ki spent equals `ceil(cost * 0.5)` on failure
  - Proficiency does NOT increase on failure
- Seed proficiency 100 and assert:
  - technique succeeds deterministically
  - Ki spent equals full cost
  - Proficiency increases by 1 on success (capped at 100)

**Step 2: Implement**

- `TechniqueMath`:
  - linear interpolation between `{at0, at100}`
  - `effectiveCost`, `effectiveDamage`, `effectiveSuccessChance`
- `DeterministicRoller`:
  - `roll(string $seed, float $p): bool` using hash-derived 0..1
  - seed includes `(sessionId, currentTick, attackerActorId, techniqueCode)` for determinism
- Executors:
  - `BlastExecutor` and `BeamExecutor` both resolve instantly (beam can share same damage model, with range from config).
  - `ChargedExecutor` uses `LocalActor` charging state:
    - first use sets `chargingTicksRemaining = chargeTicks`
    - each subsequent actor turn decrements; on 0, releases and applies instant resolution
- `CombatResolver`:
  - load `TechniqueDefinition` by code (enabled)
  - ensure attacker knows technique (`CharacterTechnique` exists)
  - compute effects from config + proficiency
  - on success: apply damage, increment proficiency (+1)
  - on failure: spend partial Ki per config, record event

**Step 3: Migrate charging columns**
Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate --no-interaction`

**Step 4: Run tests**
Run:
- `php bin/phpunit tests/Game/Application/Local/TechniqueFailureAndProficiencyTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Domain/Techniques src/Game/Application/Local/Combat src/Entity migrations tests/Game/Application/Local
git commit -m "feat: data-driven technique executors with proficiency and failure"
```

---

### Task 6: Add CLI for learning techniques (MVP)

**Files:**
- Create: `src/Command/GameCharacterLearnTechniqueCommand.php`
- Test: `tests/Game/Application/Techniques/CharacterLearnTechniqueCommandTest.php`

**Steps**
- Command: `php bin/console game:character:learn-technique --character=1 --technique=ki_blast --proficiency=0`
- Upsert `CharacterTechnique` if already exists.
- Test command returns SUCCESS and creates row.

**Commit**

```bash
git add src/Command tests/Game/Application/Techniques
git commit -m "feat: learn technique command"
```

---

## Execution Handoff

Plan complete and saved to `docs/plans/2026-01-14-data-driven-techniques.md`. Two execution options:

1. Subagent-Driven (this session)
2. Parallel Session (separate)

Which approach?

