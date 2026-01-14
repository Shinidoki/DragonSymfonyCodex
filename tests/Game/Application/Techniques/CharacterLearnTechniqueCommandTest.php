<?php

namespace App\Tests\Game\Application\Techniques;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Domain\Race;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CharacterLearnTechniqueCommandTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testCommandCreatesCharacterTechnique(): void
    {
        self::bootKernel();

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($entityManager);

        $world     = new World('seed-1');
        $character = new Character($world, 'Goku', Race::Saiyan);
        $technique = new TechniqueDefinition(
            code: 'ki_blast',
            name: 'Ki Blast',
            type: TechniqueType::Blast,
            config: ['range' => 2, 'kiCost' => 3],
            enabled: true,
            version: 1,
        );

        $entityManager->persist($world);
        $entityManager->persist($character);
        $entityManager->persist($technique);
        $entityManager->flush();

        $application = new Application(self::$kernel);
        $tester      = new CommandTester($application->find('game:character:learn-technique'));

        $exitCode = $tester->execute([
            '--character'   => (int)$character->getId(),
            '--technique'   => 'ki_blast',
            '--proficiency' => 7,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);

        $found = $entityManager->getRepository(CharacterTechnique::class)->findOneBy([
            'character' => $character,
            'technique' => $technique,
        ]);

        self::assertInstanceOf(CharacterTechnique::class, $found);
        self::assertSame(7, $found->getProficiency());
    }
}

