<?php

namespace App\Repository;

use App\Entity\Character;
use App\Entity\CharacterTechnique;
use App\Entity\TechniqueDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterTechnique>
 */
final class CharacterTechniqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterTechnique::class);
    }

    public function findOneFor(Character $character, TechniqueDefinition $technique): ?CharacterTechnique
    {
        return $this->findOneBy([
            'character' => $character,
            'technique' => $technique,
        ]);
    }
}

