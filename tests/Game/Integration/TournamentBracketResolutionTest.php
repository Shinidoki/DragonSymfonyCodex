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

final class TournamentBracketResolutionTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testResolvesTournamentAndPaysTopThreeWithSemisThirdPlaceFinale(): void
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
        $settlement->addToTreasury(5_000);

        $organizer = new Character($world, 'Mayor', Race::Human);
        $organizer->setTilePosition(3, 0);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 200]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighters = [];
        for ($i = 1; $i <= 4; $i++) {
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

        $handler->advance((int)$world->getId(), 3);

        $tournament = $entityManager->getRepository(Tournament::class)->findOneBy(['world' => $world]);
        self::assertInstanceOf(Tournament::class, $tournament);
        self::assertTrue($tournament->isResolved());

        $participants = $entityManager->getRepository(TournamentParticipant::class)->findBy(['tournament' => $tournament]);
        self::assertCount(4, $participants);

        $totalMoney = 0;
        foreach ($fighters as $fighter) {
            $entityManager->refresh($fighter);
            $totalMoney += $fighter->getMoney();
        }

        // Prize pool is 50% of spend (200) => 100, split 50/30/20.
        self::assertSame(100, $totalMoney);

        $resolvedEvent = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'type' => 'tournament_resolved',
        ]);
        self::assertInstanceOf(CharacterEvent::class, $resolvedEvent);

        $payload = $resolvedEvent->getData();
        self::assertIsArray($payload);
        self::assertSame('resolved', $payload['outcome'] ?? null);
        self::assertSame(4, $payload['participant_count'] ?? null);
        self::assertSame(4, $payload['registered_count'] ?? null);
        self::assertArrayNotHasKey('reason', $payload);
    }
}
