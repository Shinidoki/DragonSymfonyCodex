<?php

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\Settlement;
use App\Entity\Tournament;
use App\Entity\TournamentParticipant;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentCancellationTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCancelsTournamentIfFewerThanFourParticipantsRegister(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-1');
        $world->setMapSize(8, 8);
        $entityManager->persist($world);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(1_000);

        $organizer = new Character($world, 'Mayor', Race::Human);
        $organizer->setTilePosition(3, 0);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 200]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighters = [];
        for ($i = 1; $i <= 3; $i++) {
            $c = new Character($world, sprintf('F%d', $i), Race::Human);
            $c->setTilePosition(3, 0);
            $fighters[] = $c;

            $g = new CharacterGoal($c);
            $g->setLifeGoalCode('fighter.become_strongest');
            $g->setCurrentGoalCode('goal.participate_tournament');
            $g->setCurrentGoalData(['center_x' => 3, 'center_y' => 0, 'resolve_day' => 3]);
            $g->setCurrentGoalComplete(false);

            $entityManager->persist($c);
            $entityManager->persist($g);
        }

        $entityManager->persist($settlementTile);
        $entityManager->persist($settlement);
        $entityManager->persist($organizer);
        $entityManager->persist($organizerGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        // Day 1: announce; Day 2: registration closes + group stage (should cancel).
        $handler->advance((int)$world->getId(), 2);

        $tournament = $entityManager->getRepository(Tournament::class)->findOneBy(['world' => $world]);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertSame('canceled', $tournament->getStatus());

        $participants = $entityManager->getRepository(TournamentParticipant::class)->findBy(['tournament' => $tournament]);
        self::assertCount(3, $participants);

        foreach ($fighters as $fighter) {
            /** @var CharacterGoal $goal */
            $goal = $entityManager->getRepository(CharacterGoal::class)->findOneBy(['character' => $fighter]);
            self::assertSame('goal.participate_tournament', $goal->getCurrentGoalCode());
            self::assertTrue($goal->isCurrentGoalComplete());
        }

        $event = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'type'  => 'tournament_canceled',
            'day'   => 2,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $event);
        self::assertNull($event->getCharacter());
    }
}

