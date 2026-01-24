<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\CharacterTransformation;
use App\Entity\LocalActor;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Application\Local\ApplyLocalActionHandler;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Domain\LocalMap\LocalAction;
use App\Game\Domain\LocalMap\LocalActionType;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
use App\Game\Domain\Transformations\Transformation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TransformWhileChargingTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testTransformIsBlockedWhileChargingWhenProficiencyBelow50(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $player->setKiCapacity(10);
        $player->setKiControl(10);
        $player->setSpeed(999);

        $technique = new TechniqueDefinition(
            code: 'kamehameha',
            name: 'Kamehameha',
            type: TechniqueType::Charged,
            config: [
                'aimModes'               => ['dir', 'actor', 'point'],
                'delivery'               => 'ray',
                'piercing'               => 'all',
                'range'                  => 4,
                'kiCost'                 => 12,
                'chargeTicks'            => 2,
                'holdKiPerTick'          => 1,
                'allowMoveWhilePrepared' => false,
                'successChance'          => ['at0' => 1.0, 'at100' => 1.0],
            ],
            enabled: true,
            version: 1,
        );

        $em->persist($world);
        $em->persist($player);
        $em->persist($technique);
        $em->persist(new CharacterTechnique($player, $technique, proficiency: 0));
        $em->persist(new CharacterTransformation($player, Transformation::SuperSaiyan, proficiency: 49));
        $em->flush();

        $session = (new EnterLocalModeHandler($em))->enter((int)$player->getId(), 8, 8);
        $handler = new ApplyLocalActionHandler($em);

        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Technique, techniqueCode: 'kamehameha'));
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Transform, transformation: Transformation::SuperSaiyan));

        $reloaded = $em->find(Character::class, (int)$player->getId());
        self::assertInstanceOf(Character::class, $reloaded);
        self::assertNull($reloaded->getTransformationState()->active);
    }

    public function testTransformIsAllowedWhileChargingWhenProficiencyAtLeast50(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $player->setKiCapacity(10);
        $player->setKiControl(10);
        $player->setSpeed(999);

        $technique = new TechniqueDefinition(
            code: 'kamehameha',
            name: 'Kamehameha',
            type: TechniqueType::Charged,
            config: [
                'aimModes'               => ['dir', 'actor', 'point'],
                'delivery'               => 'ray',
                'piercing'               => 'all',
                'range'                  => 4,
                'kiCost'                 => 12,
                'chargeTicks'            => 2,
                'holdKiPerTick'          => 1,
                'allowMoveWhilePrepared' => false,
                'successChance'          => ['at0' => 1.0, 'at100' => 1.0],
            ],
            enabled: true,
            version: 1,
        );

        $em->persist($world);
        $em->persist($player);
        $em->persist($technique);
        $em->persist(new CharacterTechnique($player, $technique, proficiency: 0));
        $em->persist(new CharacterTransformation($player, Transformation::SuperSaiyan, proficiency: 50));
        $em->flush();

        $session = (new EnterLocalModeHandler($em))->enter((int)$player->getId(), 8, 8);
        $handler = new ApplyLocalActionHandler($em);

        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Technique, techniqueCode: 'kamehameha'));
        $handler->apply((int)$session->getId(), new LocalAction(LocalActionType::Transform, transformation: Transformation::SuperSaiyan));

        $reloaded = $em->find(Character::class, (int)$player->getId());
        self::assertInstanceOf(Character::class, $reloaded);
        self::assertSame(Transformation::SuperSaiyan, $reloaded->getTransformationState()->active);

        /** @var LocalActor $playerActor */
        $playerActor = $em->getRepository(LocalActor::class)->findOneBy(['session' => $session, 'role' => 'player']);
        self::assertInstanceOf(LocalActor::class, $playerActor);
        self::assertTrue($playerActor->hasPreparedTechnique());
    }
}

