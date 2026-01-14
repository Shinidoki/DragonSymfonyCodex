<?php

namespace App\Repository;

use App\Entity\Character;
use App\Entity\CharacterTransformation;
use App\Game\Domain\Transformations\Transformation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CharacterTransformation>
 */
final class CharacterTransformationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CharacterTransformation::class);
    }

    public function findOneFor(Character $character, Transformation $transformation): ?CharacterTransformation
    {
        return $this->findOneBy([
            'character'      => $character,
            'transformation' => $transformation,
        ]);
    }
}

