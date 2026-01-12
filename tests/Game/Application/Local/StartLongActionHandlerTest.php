<?php

namespace App\Tests\Game\Application\Local;

use App\Entity\Character;
use App\Entity\World;
use App\Game\Application\Local\EnterLocalModeHandler;
use App\Game\Application\Local\LongActionType;
use App\Game\Application\Local\StartLongActionHandler;
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
}

