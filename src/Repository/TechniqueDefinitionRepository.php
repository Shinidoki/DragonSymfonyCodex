<?php

namespace App\Repository;

use App\Entity\TechniqueDefinition;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TechniqueDefinition>
 */
final class TechniqueDefinitionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TechniqueDefinition::class);
    }

    public function findEnabledByCode(string $code): ?TechniqueDefinition
    {
        return $this->findOneBy([
            'code'    => strtolower(trim($code)),
            'enabled' => true,
        ]);
    }
}

