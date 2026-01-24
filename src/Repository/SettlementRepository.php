<?php

namespace App\Repository;

use App\Entity\Settlement;
use App\Entity\World;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settlement>
 */
class SettlementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settlement::class);
    }

    /**
     * @return list<Settlement>
     */
    public function findByWorld(World $world): array
    {
        /** @var list<Settlement> $settlements */
        $settlements = $this->findBy(['world' => $world], ['id' => 'ASC']);

        return $settlements;
    }

    public function findOneByWorldCoord(World $world, int $x, int $y): ?Settlement
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('x and y must be >= 0.');
        }

        $settlement = $this->findOneBy(['world' => $world, 'x' => $x, 'y' => $y]);
        if (!$settlement instanceof Settlement) {
            return null;
        }

        return $settlement;
    }
}
