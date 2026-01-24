<?php

namespace App\Tests\Game\Application\Character;

use App\Entity\Character;
use App\Entity\CharacterTransformation;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Transformations\Transformation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CharacterLearnTransformationCommandTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCommandCreatesAndUpdatesTransformationProficiency(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:character:learn-transformation'));

        $exitCode = $tester->execute([
            '--character'      => (int)$character->getId(),
            '--transformation' => 'super_saiyan',
            '--proficiency'    => 51,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $row = $entityManager->getRepository(CharacterTransformation::class)->findOneBy([
            'character'      => $character,
            'transformation' => Transformation::SuperSaiyan,
        ]);
        self::assertInstanceOf(CharacterTransformation::class, $row);
        self::assertSame(51, $row->getProficiency());

        $exitCode = $tester->execute([
            '--character'      => (int)$character->getId(),
            '--transformation' => 'super_saiyan',
            '--proficiency'    => 7,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $entityManager->clear();
        $reloadedCharacter = $entityManager->find(Character::class, (int)$character->getId());
        self::assertInstanceOf(Character::class, $reloadedCharacter);

        $row = $entityManager->getRepository(CharacterTransformation::class)->findOneBy([
            'character'      => $reloadedCharacter,
            'transformation' => Transformation::SuperSaiyan,
        ]);
        self::assertInstanceOf(CharacterTransformation::class, $row);
        self::assertSame(7, $row->getProficiency());
    }
}
