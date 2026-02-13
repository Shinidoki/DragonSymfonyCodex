<?php

namespace App\Tests\Game\Domain\Combat\SimulatedCombat;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use App\Entity\World;
use App\Game\Domain\Combat\SimulatedCombat\CombatRules;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatant;
use App\Game\Domain\Combat\SimulatedCombat\SimulatedCombatResolver;
use App\Game\Domain\Race;
use App\Game\Domain\Random\SequenceRandomizer;
use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Techniques\TechniqueType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class SimulatedCombatResolverTest extends KernelTestCase
{
    private function resetDatabaseSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $tool     = new SchemaTool($entityManager);
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
    }

    public function testChargedTechniqueCanResolveFightRoundByRound(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $world = new World('seed-1');
        $em->persist($world);

        $attacker = new Character($world, 'Attacker', Race::Human);
        $defender = new Character($world, 'Defender', Race::Human);

        $attacker->applyCoreAttributes(new CoreAttributes(
            strength: 5,
            speed: 5,
            endurance: 5,
            durability: 1,
            kiCapacity: 5,
            kiControl: 10,
            kiRecovery: 1,
            focus: 1,
            discipline: 1,
            adaptability: 1,
        ));

        $defender->applyCoreAttributes(new CoreAttributes(
            strength: 1,
            speed: 1,
            endurance: 3,
            durability: 1,
            kiCapacity: 1,
            kiControl: 1,
            kiRecovery: 1,
            focus: 1,
            discipline: 1,
            adaptability: 1,
        ));

        $em->persist($attacker);
        $em->persist($defender);
        $em->flush();

        $charged = new TechniqueDefinition(
            code: 'big_beam',
            name: 'Big Beam',
            type: TechniqueType::Charged,
            config: [
                'delivery'      => 'single',
                'aimModes'      => ['actor'],
                'kiCost'        => 1,
                'chargeTicks'   => 1,
                'damage'        => [
                    'stat'              => 'kiControl',
                    'statMultiplier'    => 1.0,
                    'base'              => 5,
                    'min'               => 1,
                    'mitigationStat'    => 'durability',
                    'mitigationDivisor' => 2,
                ],
                'successChance' => ['at0' => 1.0, 'at100' => 1.0],
            ],
        );

        $knowledge = new CharacterTechnique($attacker, $charged, proficiency: 100);

        $resolver = new SimulatedCombatResolver(new SequenceRandomizer([0]));

        $result = $resolver->resolve(
            combatants: [
                new SimulatedCombatant($attacker, teamId: (int)$attacker->getId(), techniques: [$knowledge]),
                new SimulatedCombatant($defender, teamId: (int)$defender->getId()),
            ],
            rules: new CombatRules(allowFriendlyFire: true, maxActions: 50, allowTransform: false),
        );

        self::assertSame((int)$attacker->getId(), $result->winnerCharacterId);
        self::assertNotEmpty($result->log);
        self::assertStringContainsString('starts charging', $result->log[0]);
    }

    public function testFriendlyFireDisabledDoesNotTargetFriendlies(): void
    {
        self::bootKernel();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $this->resetDatabaseSchema($em);

        $world = new World('seed-2');
        $em->persist($world);

        $a = new Character($world, 'A', Race::Human);
        $b = new Character($world, 'B', Race::Human);
        $c = new Character($world, 'C', Race::Human);

        $a->applyCoreAttributes(new CoreAttributes(
            strength: 1,
            speed: 5,
            endurance: 5,
            durability: 1,
            kiCapacity: 5,
            kiControl: 10,
            kiRecovery: 1,
            focus: 1,
            discipline: 1,
            adaptability: 1,
        ));

        $em->persist($a);
        $em->persist($b);
        $em->persist($c);
        $em->flush();

        $aoe = new TechniqueDefinition(
            code: 'burst',
            name: 'Burst',
            type: TechniqueType::Blast,
            config: [
                'delivery'      => 'aoe',
                'aimModes'      => ['self'],
                'kiCost'        => 1,
                'damage'        => [
                    'stat'           => 'kiControl',
                    'statMultiplier' => 1.0,
                    'base'           => 10,
                    'min'            => 1,
                ],
                'successChance' => ['at0' => 1.0, 'at100' => 1.0],
            ],
        );

        $knowledge = new CharacterTechnique($a, $aoe, proficiency: 100);

        $resolver = new SimulatedCombatResolver(new SequenceRandomizer([0]));

        $result = $resolver->resolve(
            combatants: [
                new SimulatedCombatant($a, teamId: 1, techniques: [$knowledge]),
                new SimulatedCombatant($b, teamId: 1),
                new SimulatedCombatant($c, teamId: 2),
            ],
            rules: new CombatRules(allowFriendlyFire: false, maxActions: 1, allowTransform: false),
        );

        $joined = implode("\n", $result->log);
        self::assertStringContainsString('uses Burst on C', $joined);
        self::assertStringNotContainsString('uses Burst on B', $joined);
    }
}
