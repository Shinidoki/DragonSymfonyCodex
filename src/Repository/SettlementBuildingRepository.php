<?php

namespace App\Repository;

use App\Entity\Settlement;
use App\Entity\SettlementBuilding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SettlementBuilding>
 */
class SettlementBuildingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SettlementBuilding::class);
    }

    public function findOneBySettlementAndCode(Settlement $settlement, string $code): ?SettlementBuilding
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            throw new \InvalidArgumentException('code must not be empty.');
        }

        $b = $this->findOneBy(['settlement' => $settlement, 'code' => $code]);
        if (!$b instanceof SettlementBuilding) {
            return null;
        }

        return $b;
    }
}
