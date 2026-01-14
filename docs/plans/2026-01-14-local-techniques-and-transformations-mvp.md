# Local Techniques + Transformations MVP Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add the first Dragon Ball–flavored combat knobs by introducing (1) persistent transformation state on characters, (2) a Ki pool for local combatants, and (3) one technique (`ki_blast`) usable in local mode/combat with deterministic costs and effects.

**Architecture:** Keep rules in pure PHP under `src/Game/Domain/*` (new `Techniques` domain + existing `Transformations` domain) and keep persistence in Doctrine entities (`Character`, `LocalCombatant`). Wire everything through the existing local tick pipeline (`LocalTurnEngine` + `CombatResolver`) so every “transform/technique” is still exactly one tick.

**Tech Stack:** Symfony 8, PHP 8.4, Doctrine ORM/Migrations, PHPUnit.

## What’s Left (from `docs/`)

The docs set a broad design target (stats, NPCs, world map, local mode, combat). Most “Simulation MVP / Local mode / Turn scheduler / Combat MVP” pieces already exist in `src/Game` + `src/Entity`.

The clearest remaining “next layer” called out explicitly as non-goals in `docs/plans/2026-01-12-local-combat-turn-scheduler.md` is:

- Techniques (Ki costs, range, effects) from `docs/race_techniques.md`
- Transformations in combat (activation/deactivation as tick actions, post-use exhaustion) from `docs/transformations.md`

This plan implements the smallest slice of that layer.

---

### Task 1: Persist transformation state on `Character` and recover it during day simulation

**Files:**
- Modify: `src/Entity/Character.php`
- Modify: `src/Game/Domain/Simulation/SimulationClock.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Domain/Transformations/TransformationStatePersistenceTest.php`

**Step 1: Write the failing test**

Create an integration-ish test that:
- boots the kernel
- creates a `World` + `Character`
- sets a transformation state on the character (active + ticks + exhaustion)
- flushes, reloads the character
- asserts the state is preserved

```php
<?php
// tests/Game/Domain/Transformations/TransformationStatePersistenceTest.php
namespace App\Tests\Game\Domain\Transformations;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransformationStatePersistenceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCharacterPersistsTransformationState(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $c     = new Character($world, 'Goku', Race::Saiyan);
        $c->setTransformationState(new TransformationState(
            active: Transformation::SuperSaiyan,
            activeTicks: 2,
            exhaustionDaysRemaining: 1,
        ));

        $entityManager->persist($world);
        $entityManager->persist($c);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find(Character::class, (int)$c->getId());
        self::assertInstanceOf(Character::class, $reloaded);

        $state = $reloaded->getTransformationState();
        self::assertSame(Transformation::SuperSaiyan, $state->active);
        self::assertSame(2, $state->activeTicks);
        self::assertSame(1, $state->exhaustionDaysRemaining);
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Transformations/TransformationStatePersistenceTest.php`
Expected: FAIL (missing `Character` API and/or unmapped columns).

**Step 3: Implement minimal persistence**

In `src/Entity/Character.php`:
- Add columns:
  - `transformationActive` (nullable string or enum)
  - `transformationActiveTicks` (int, default 0)
  - `transformationExhaustionDaysRemaining` (int, default 0)
- Add methods:
  - `getTransformationState(): TransformationState`
  - `setTransformationState(TransformationState $state): void`

Implementation notes:
- Prefer using the existing enum: `#[ORM\Column(enumType: Transformation::class, nullable: true)]` for `active`.
- Validate non-negative ints in setters.

In `src/Game/Domain/Simulation/SimulationClock.php`:
- During `advanceDays()` and `advanceDaysForLongAction()`, recover transformation exhaustion by one day per day advanced:
  - `$character->setTransformationState((new TransformationService())->advanceDay($character->getTransformationState()));`
- For MVP, if a character is actively transformed during day sim, force it to deactivate first (transformations are tick-scoped):
  - if state has `active !== null`, set to `deactivate()` before applying `advanceDay()`.

**Step 4: Add migration**

Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

Expected: migration runs cleanly.

**Step 5: Re-run test**

Run: `php bin/phpunit tests/Game/Domain/Transformations/TransformationStatePersistenceTest.php`
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Game/Domain/Simulation migrations tests/Game/Domain/Transformations
git commit -m "feat: persist character transformation state"
```

---

### Task 2: Apply transformation effects to speed/strength/durability in local turns + combat

**Files:**
- Modify: `src/Game/Application/Local/LocalTurnEngine.php`
- Modify: `src/Game/Application/Local/Combat/CombatResolver.php`
- Test: `tests/Game/Application/Local/LocalCombatTransformationTest.php`

**Step 1: Write the failing test**

Create a flow test that proves transformations impact combat deterministically:
- Player has low base STR but is transformed to double STR.
- Without transformation, damage would be low; with transformation, damage is higher.

```php
<?php
// tests/Game/Application/Local/LocalCombatTransformationTest.php
namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombatant;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatTransformationTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTransformationBoostsCombatDamage(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setStrength(2);
        $player->setTransformationState(new TransformationState(active: Transformation::SuperSaiyan, activeTicks: 0, exhaustionDaysRemaining: 0));

        $npc->setEndurance(1);
        $npc->setDurability(1);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 8, 8);

        /** @var LocalActor $playerActor */
        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        $playerActor->setPosition(4, 4);
        $session->setPlayerPosition(4, 4);
        $entityManager->flush();

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 5);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        (new ApplyLocalActionHandler($entityManager))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Attack, targetActorId: (int)$npcActor->getId()),
        );

        /** @var list<LocalCombatant> $combatants */
        $combatants = $entityManager->getRepository(LocalCombatant::class)->findBy([], ['id' => 'ASC']);
        self::assertNotEmpty($combatants);

        $npcCombatant = null;
        foreach ($combatants as $c) {
            if ($c->getActorId() === (int)$npcActor->getId()) {
                $npcCombatant = $c;
                break;
            }
        }
        self::assertInstanceOf(LocalCombatant::class, $npcCombatant);

        // With SSJ multiplier 2.0, STR=4, durability=1 => damage = max(1, 4 - 0) = 4
        self::assertSame($npcCombatant->getMaxHp() - 4, $npcCombatant->getCurrentHp());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatTransformationTest.php`
Expected: FAIL (combat uses base stats).

**Step 3: Implement transformation-aware attributes**

In `src/Game/Application/Local/LocalTurnEngine.php`:
- When computing speed in `buildTurnState()`, use effective attributes:
  - `$effective = (new TransformationService())->effectiveAttributes($character->getCoreAttributes(), $character->getTransformationState());`
  - speed = `max(1, $effective->speed)`

In `src/Game/Application/Local/Combat/CombatResolver.php`:
- When computing `maxHp()` and `damagePerHit()`, use transformation-aware attributes for both attacker and defender via `TransformationService::effectiveAttributes()`.

**Step 4: Re-run test**

Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatTransformationTest.php`
Expected: PASS.

**Step 5: Commit**

```bash
git add src/Game/Application tests/Game/Application/Local
git commit -m "feat: apply transformation effects in local turns and combat"
```

---

### Task 3: Add Ki pool to `LocalCombatant` (max/current) with deterministic formulas

**Files:**
- Modify: `src/Entity/LocalCombatant.php`
- Modify: `src/Game/Application/Local/Combat/CombatResolver.php`
- Create: `migrations/VersionYYYYMMDDHHMMSS.php` (via doctrine diff)
- Test: `tests/Game/Application/Local/LocalCombatantKiPersistenceTest.php`

**Step 1: Write the failing test**

Persist a combatant and assert Ki fields are present and preserved.

```php
<?php
// tests/Game/Application/Local/LocalCombatantKiPersistenceTest.php
namespace App\Tests\Game\Application\Local;

use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalCombatantKiPersistenceTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCombatantPersistsKiFields(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $session = new LocalSession(worldId: 1, characterId: 1, tileX: 0, tileY: 0, width: 8, height: 8, playerX: 4, playerY: 4);
        $combat  = new LocalCombat($session);
        $c       = new LocalCombatant($combat, actorId: 1, maxHp: 10, maxKi: 12);

        $entityManager->persist($session);
        $entityManager->persist($combat);
        $entityManager->persist($c);
        $entityManager->flush();
        $entityManager->clear();

        $reloaded = $entityManager->find(LocalCombatant::class, 1);
        self::assertInstanceOf(LocalCombatant::class, $reloaded);
        self::assertSame(12, $reloaded->getMaxKi());
        self::assertSame(12, $reloaded->getCurrentKi());
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatantKiPersistenceTest.php`
Expected: FAIL (constructor/fields missing).

**Step 3: Implement Ki fields**

In `src/Entity/LocalCombatant.php`:
- Add columns:
  - `maxKi` (int)
  - `currentKi` (int)
- Extend constructor signature to accept `maxKi` and initialize `currentKi = maxKi`.
- Add methods:
  - `getMaxKi(): int`
  - `getCurrentKi(): int`
  - `spendKi(int $amount): bool` (returns false if insufficient)

In `src/Game/Application/Local/Combat/CombatResolver.php`:
- When creating a combatant, compute max Ki deterministically from *effective* attributes:
  - `maxKi = 5 + (kiCapacity * 3) + kiControl`

**Step 4: Add migration**

Run:
- `php bin/console doctrine:migrations:diff`
- `php bin/console doctrine:migrations:migrate`

Expected: migration runs cleanly.

**Step 5: Re-run test**

Run: `php bin/phpunit tests/Game/Application/Local/LocalCombatantKiPersistenceTest.php`
Expected: PASS.

**Step 6: Commit**

```bash
git add src/Entity src/Game/Application/Local/Combat migrations tests/Game/Application/Local
git commit -m "feat: add ki pool to local combatants"
```

---

### Task 4: Add a minimal technique system and `ki_blast` local action

**Files:**
- Create: `src/Game/Domain/Techniques/Technique.php`
- Create: `src/Game/Domain/Techniques/TechniqueCatalog.php`
- Modify: `src/Game/Domain/LocalMap/LocalActionType.php`
- Modify: `src/Game/Domain/LocalMap/LocalAction.php`
- Modify: `src/Game/Application/Local/LocalTurnEngine.php`
- Modify: `src/Command/GameLocalActionCommand.php`
- Modify: `src/Controller/Api/LocalSessionController.php`
- Modify: `src/Game/Application/Local/Combat/CombatResolver.php`
- Test: `tests/Game/Domain/Techniques/TechniqueCatalogTest.php`
- Test: `tests/Game/Application/Local/LocalKiBlastFlowTest.php`

**Step 1: Write the failing unit test (catalog)**

```php
<?php
// tests/Game/Domain/Techniques/TechniqueCatalogTest.php
namespace App\Tests\Game\Domain\Techniques;

use App\Game\Domain\Techniques\Technique;
use App\Game\Domain\Techniques\TechniqueCatalog;
use PHPUnit\Framework\TestCase;

final class TechniqueCatalogTest extends TestCase
{
    public function testKiBlastHasCostAndRange(): void
    {
        $c = new TechniqueCatalog();

        self::assertSame(3, $c->kiCost(Technique::KiBlast));
        self::assertSame(2, $c->range(Technique::KiBlast));
    }
}
```

**Step 2: Run test to verify it fails**

Run: `php bin/phpunit tests/Game/Domain/Techniques/TechniqueCatalogTest.php`
Expected: FAIL (missing classes).

**Step 3: Implement `Technique` + `TechniqueCatalog`**

```php
<?php
// src/Game/Domain/Techniques/Technique.php
namespace App\Game\Domain\Techniques;

enum Technique: string
{
    case KiBlast = 'ki_blast';
}
```

```php
<?php
// src/Game/Domain/Techniques/TechniqueCatalog.php
namespace App\Game\Domain\Techniques;

final class TechniqueCatalog
{
    public function kiCost(Technique $technique): int
    {
        return match ($technique) {
            Technique::KiBlast => 3,
        };
    }

    public function range(Technique $technique): int
    {
        return match ($technique) {
            Technique::KiBlast => 2,
        };
    }
}
```

**Step 4: Re-run unit test**

Run: `php bin/phpunit tests/Game/Domain/Techniques/TechniqueCatalogTest.php`
Expected: PASS.

**Step 5: Write the failing flow test (`ki_blast`)**

The flow should prove:
- `ki_blast` spends Ki
- `ki_blast` deals deterministic damage at range 2
- emits an event message

```php
<?php
// tests/Game/Application/Local/LocalKiBlastFlowTest.php
namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LocalEventLog;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class LocalKiBlastFlowTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testKiBlastSpendsKiAndDealsDamageAtRange(): void
    {
        self::bootKernel();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $player->setKiCapacity(5);
        $player->setKiControl(5);
        $player->setSpeed(999);

        $npc->setEndurance(1);
        $npc->setDurability(1);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $session = (new EnterLocalModeHandler($entityManager))->enter((int)$player->getId(), 8, 8);
        $session->setPlayerPosition(4, 4);

        /** @var LocalActor $playerActor */
        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        $playerActor->setPosition(4, 4);
        $entityManager->flush();

        // Range 2: target at (4,6) is distance 2
        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 4, y: 6);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        (new ApplyLocalActionHandler($entityManager))->apply(
            (int)$session->getId(),
            new LocalAction(LocalActionType::Technique, targetActorId: (int)$npcActor->getId(), technique: 'ki_blast'),
        );

        /** @var list<LocalCombatant> $combatants */
        $combatants = $entityManager->getRepository(LocalCombatant::class)->findBy([], ['id' => 'ASC']);
        self::assertNotEmpty($combatants);

        $attackerCombatant = null;
        foreach ($combatants as $c) {
            if ($c->getActorId() === (int)$playerActor->getId()) {
                $attackerCombatant = $c;
                break;
            }
        }
        self::assertInstanceOf(LocalCombatant::class, $attackerCombatant);
        self::assertLessThan($attackerCombatant->getMaxKi(), $attackerCombatant->getCurrentKi());

        $messages = (new LocalEventLog($entityManager))->drainMessages((int)$session->getId());
        self::assertTrue((bool)preg_grep('/ki blast/i', $messages));
    }
}
```

**Step 6: Run flow test to verify it fails**

Run: `php bin/phpunit tests/Game/Application/Local/LocalKiBlastFlowTest.php`
Expected: FAIL (LocalActionType/CombatResolver don’t support technique).

**Step 7: Implement minimal wiring**

In `src/Game/Domain/LocalMap/LocalActionType.php`:
- Add `case Technique = 'technique';`

In `src/Game/Domain/LocalMap/LocalAction.php`:
- Add a new optional field (string or enum):
  - `public ?string $technique = null`
- Validate: when type is `Technique`, both `targetActorId` and `technique` must be present.

In `src/Command/GameLocalActionCommand.php`:
- Accept `--type=technique`
- Add `--technique=ki_blast` option required for `technique`

In `src/Game/Application/Local/LocalTurnEngine.php`:
- Add handling for `LocalActionType::Technique` and call a new resolver path:
  - `(new CombatResolver(...))->useTechnique($session, $playerActor, $action->targetActorId, Technique::from($action->technique))`

In `src/Game/Application/Local/Combat/CombatResolver.php`:
- Add `useTechnique(...)` for `Technique::KiBlast`:
  - validate target in session
  - validate range <= catalog range (use Manhattan distance)
  - ensure combat + combatants exist
  - spend Ki from attacker combatant (if insufficient: emit event + no damage)
  - compute damage deterministically using *effective* attributes:
    - `damage = max(1, kiControl - intdiv(durability, 2))`
  - apply damage and events similar to `attack()`, but message includes “Ki Blast”

In `src/Controller/Api/LocalSessionController.php`:
- Include combatant Ki in payload:
  - `maxKi`, `currentKi`

**Step 8: Re-run tests**

Run:
- `php bin/phpunit tests/Game/Domain/Techniques/TechniqueCatalogTest.php`
- `php bin/phpunit tests/Game/Application/Local/LocalKiBlastFlowTest.php`

Expected: PASS.

**Step 9: Commit**

```bash
git add src/Game/Domain src/Game/Application src/Command src/Controller tests
git commit -m "feat: add ki blast technique action with ki costs"
```

---

## Manual Verification Script (CLI)

Run (example ids will differ):

```bash
php bin/console game:world:create --seed=earth-0001
php bin/console game:world:generate-map --world=1 --width=8 --height=8
php bin/console game:character:create --world=1 --name=Goku --race=saiyan
php bin/console game:character:create --world=1 --name=Krillin --race=human

php bin/console game:local:enter --character=1 --width=8 --height=8
php bin/console game:local:add-actor --session=1 --character=2 --role=npc --x=4 --y=6

php bin/console game:local:action --session=1 --type=technique --target=2 --technique=ki_blast
php bin/console game:local:action --session=1 --type=attack --target=2
```

Expected:
- `ki_blast` prints a nearby message mentioning “Ki Blast” and reduces attacker Ki.
- combatant HP decreases deterministically.

