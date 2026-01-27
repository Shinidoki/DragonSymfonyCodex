<?php

namespace App\Repository;

use App\Entity\Settlement;
use App\Entity\SettlementProject;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SettlementProject>
 */
class SettlementProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SettlementProject::class);
    }

    public function findActiveForSettlement(Settlement $settlement): ?SettlementProject
    {
        $p = $this->findOneBy(['settlement' => $settlement, 'status' => SettlementProject::STATUS_ACTIVE]);
        if (!$p instanceof SettlementProject) {
            return null;
        }

        return $p;
    }
}
