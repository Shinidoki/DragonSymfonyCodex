<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalActor;
use App\Entity\LocalIntent;
use App\Entity\NpcProfile;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LongActionType;
use App\Game\Application\Local\StartLongActionHandler;
use App\Game\Domain\LocalNpc\IntentType;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use App\Game\Domain\Simulation\SimulationClock;
use App\Game\Domain\Stats\Growth\TrainingGrowthService;
use App\Game\Domain\Training\TrainingContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class StartLongActionHandlerTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testLongTrainSuspendsAdvancesDaysResumesAndKeepsLocalPosition(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$character->getId(), 8, 8);
        $session->setPlayerPosition(1, 1);
        $entityManager->flush();

        $startStrength = $character->getStrength();

        $clock   = new SimulationClock(new TrainingGrowthService());
        $handler = new StartLongActionHandler($entityManager, $clock);

        $result = $handler->start(
            sessionId: (int)$session->getId(),
            days: 7,
            type: LongActionType::Train,
            trainingContext: TrainingContext::Dojo,
        );

        self::assertSame(7, $result->daysAdvanced);
        self::assertSame(7, $result->world->getCurrentDay());

        self::assertSame('active', $result->session->getStatus());
        self::assertSame(1, $result->session->getPlayerX());
        self::assertSame(1, $result->session->getPlayerY());

        self::assertSame($startStrength + (7 * 3), $result->character->getStrength());
    }

    public function testLongActionsDoNotMoveLocalNpcActors(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world  = new World('seed-1');
        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Krillin', Race::Human);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$player->getId(), 8, 8);

        $playerActor = $entityManager->getRepository(LocalActor::class)->findOneBy([
            'session' => $session,
            'role'    => 'player',
        ]);
        self::assertInstanceOf(LocalActor::class, $playerActor);

        $npcActor = new LocalActor($session, characterId: (int)$npc->getId(), role: 'npc', x: 0, y: 0);
        $entityManager->persist($npcActor);
        $entityManager->flush();

        $intent = new LocalIntent($npcActor, IntentType::MoveTo, targetActorId: (int)$playerActor->getId());
        $entityManager->persist($intent);
        $entityManager->flush();

        $clock   = new SimulationClock(new TrainingGrowthService());
        $handler = new StartLongActionHandler($entityManager, $clock);

        $handler->start(
            sessionId: (int)$session->getId(),
            days: 2,
            type: LongActionType::Train,
            trainingContext: TrainingContext::Wilderness,
        );

        $reloadedNpcActor = $entityManager->find(LocalActor::class, (int)$npcActor->getId());
        self::assertInstanceOf(LocalActor::class, $reloadedNpcActor);
        self::assertSame(0, $reloadedNpcActor->getX());
        self::assertSame(0, $reloadedNpcActor->getY());
    }

    public function testLongActionsAdvanceFighterNpcTowardNearestDojo(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);

        $player = new Character($world, 'Goku', Race::Saiyan);
        $npc    = new Character($world, 'Tien', Race::Human);
        $npc->setTilePosition(0, 0);

        $dojo = new WorldMapTile($world, 2, 0, Biome::City);
        $dojo->setHasSettlement(true);
        $dojo->setHasDojo(true);

        $npcProfile = new NpcProfile($npc, NpcArchetype::Fighter);

        $entityManager->persist($world);
        $entityManager->persist($player);
        $entityManager->persist($npc);
        $entityManager->persist($dojo);
        $entityManager->persist($npcProfile);
        $entityManager->flush();

        $enter   = new EnterLocalModeHandler($entityManager);
        $session = $enter->enter((int)$player->getId(), 8, 8);

        $clock   = new SimulationClock(new TrainingGrowthService());
        $handler = new StartLongActionHandler($entityManager, $clock);

        $handler->start(
            sessionId: (int)$session->getId(),
            days: 1,
            type: LongActionType::Train,
            trainingContext: TrainingContext::Wilderness,
        );

        $entityManager->refresh($npc);

        self::assertSame(1, $npc->getTileX());
        self::assertSame(0, $npc->getTileY());
        self::assertTrue($npc->hasTravelTarget());
        self::assertSame(2, $npc->getTargetTileX());
        self::assertSame(0, $npc->getTargetTileY());
    }
}
