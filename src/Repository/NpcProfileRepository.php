<?php

namespace App\Repository;

use App\Entity\NpcProfile;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<NpcProfile>
 */
class NpcProfileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, NpcProfile::class);
    }

    /**
     * @return list<NpcProfile>
     */
    public function findByWorld(World $world): array
    {
        /** @var list<NpcProfile> $profiles */
        $profiles = $this->createQueryBuilder('p')
            ->join('p.character', 'c')
            ->andWhere('c.world = :world')
            ->setParameter('world', $world)
            ->getQuery()
            ->getResult();

        return $profiles;
    }

    public function countForWorld(World $world): int
    {
        return (int)$this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.character', 'c')
            ->andWhere('c.world = :world')
            ->setParameter('world', $world)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

