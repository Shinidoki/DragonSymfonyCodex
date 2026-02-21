<?php

declare(strict_types=1);

namespace App\Tests\Game\Integration;

use App\Entity\Character;
use App\Entity\CharacterEvent;
use App\Entity\CharacterGoal;
use App\Entity\NpcProfile;
use App\Entity\Settlement;
use App\Entity\World;
use App\Entity\WorldMapTile;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Domain\Map\Biome;
use App\Game\Domain\Npc\NpcArchetype;
use App\Game\Domain\Race;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TournamentInterestEventLoopTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testInterestCommitEventLeadsToParticipateGoalOnFollowingDay(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-interest');
        $world->setMapSize(8, 8);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(1000);

        $organizer = new Character($world, 'Organizer', Race::Human);
        $organizer->setTilePosition(3, 0);

        $fighter = new Character($world, 'Fighter', Race::Human);
        $fighter->setTilePosition(3, 0);

        $fighterProfile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 200]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighterGoal = new CharacterGoal($fighter);
        $fighterGoal->setLifeGoalCode('fighter.become_strongest');
        $fighterGoal->setCurrentGoalCode('goal.earn_money');
        $fighterGoal->setCurrentGoalData(['target_amount' => 100]);
        $fighterGoal->setCurrentGoalComplete(false);
        $fighterGoal->setLastResolvedDay(0);

        $entityManager->persist($world);
        $entityManager->persist($settlementTile);
        $entityManager->persist($settlement);
        $entityManager->persist($organizer);
        $entityManager->persist($fighter);
        $entityManager->persist($fighterProfile);
        $entityManager->persist($organizerGoal);
        $entityManager->persist($fighterGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int) $world->getId(), 2);

        $entityManager->refresh($world);
        $entityManager->refresh($fighter);
        $entityManager->refresh($fighterGoal);

        $evaluated = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $fighter,
            'type' => 'tournament_interest_evaluated',
            'day' => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $evaluated);

        $committed = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $fighter,
            'type' => 'tournament_interest_committed',
            'day' => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $committed);
        self::assertSame('committed', $committed->getData()['decision'] ?? null);
        self::assertSame(2, $committed->getData()['registration_close_day'] ?? null);
        self::assertSame(3, $committed->getData()['resolve_day'] ?? null);

        self::assertSame('goal.participate_tournament', $fighterGoal->getCurrentGoalCode());
        self::assertFalse($fighterGoal->isCurrentGoalComplete());
    }

    public function testDeclinedInterestDoesNotSetParticipateGoalFromAnnouncement(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world = new World('seed-interest-declined');
        $world->setMapSize(8, 8);

        $settlementTile = new WorldMapTile($world, 3, 0, Biome::City);
        $settlementTile->setHasSettlement(true);

        $settlement = new Settlement($world, 3, 0);
        $settlement->addToTreasury(1000);

        $organizer = new Character($world, 'Organizer', Race::Human);
        $organizer->setTilePosition(3, 0);

        $fighter = new Character($world, 'Rich Fighter', Race::Human);
        $fighter->setTilePosition(5, 0);
        $fighter->addMoney(1_000);
        $fighter->setEmployment('guard', 3, 0);

        $fighterProfile = new NpcProfile($fighter, NpcArchetype::Fighter);

        $organizerGoal = new CharacterGoal($organizer);
        $organizerGoal->setLifeGoalCode('civilian.organize_events');
        $organizerGoal->setCurrentGoalCode('goal.organize_tournament');
        $organizerGoal->setCurrentGoalData(['spend' => 20]);
        $organizerGoal->setCurrentGoalComplete(false);

        $fighterGoal = new CharacterGoal($fighter);
        $fighterGoal->setLifeGoalCode('fighter.become_strongest');
        $fighterGoal->setCurrentGoalCode('goal.earn_money');
        $fighterGoal->setCurrentGoalData(['target_amount' => 100]);
        $fighterGoal->setCurrentGoalComplete(false);
        $fighterGoal->setLastResolvedDay(0);

        $entityManager->persist($world);
        $entityManager->persist($settlementTile);
        $entityManager->persist($settlement);
        $entityManager->persist($organizer);
        $entityManager->persist($fighter);
        $entityManager->persist($fighterProfile);
        $entityManager->persist($organizerGoal);
        $entityManager->persist($fighterGoal);
        $entityManager->flush();

        $handler = self::getContainer()->get(AdvanceDayHandler::class);
        self::assertInstanceOf(AdvanceDayHandler::class, $handler);

        $handler->advance((int) $world->getId(), 2);

        $entityManager->refresh($world);
        $entityManager->refresh($fighter);
        $entityManager->refresh($fighterGoal);

        $evaluated = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $fighter,
            'type' => 'tournament_interest_evaluated',
            'day' => 1,
        ]);
        self::assertInstanceOf(CharacterEvent::class, $evaluated);
        self::assertSame('declined', $evaluated->getData()['decision'] ?? null);

        $committed = $entityManager->getRepository(CharacterEvent::class)->findOneBy([
            'world' => $world,
            'character' => $fighter,
            'type' => 'tournament_interest_committed',
            'day' => 1,
        ]);
        self::assertNull($committed);

        self::assertSame('goal.earn_money', $fighterGoal->getCurrentGoalCode());
        self::assertFalse($fighterGoal->isCurrentGoalComplete());
    }
}
