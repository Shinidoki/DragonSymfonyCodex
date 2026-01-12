<?php

namespace App\Repository;

use App\Entity\LocalEvent;
use App\Entity\LocalSession;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LocalEvent>
 */
class LocalEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LocalEvent::class);
    }

    /**
     * @return list<LocalEvent>
     */
    public function findForSession(LocalSession $session): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.session = :session')
            ->setParameter('session', $session)
            ->orderBy('e.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

