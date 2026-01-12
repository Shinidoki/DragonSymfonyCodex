<?php

namespace App\Game\Application\World;

use App\Entity\World;
use Doctrine\ORM\EntityManagerInterface;

final class CreateWorldHandler
{
    public function __construct(
        private readonly WorldFactory           $worldFactory,
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function create(string $seed): World
    {
        $world = $this->worldFactory->create($seed);

        $this->entityManager->persist($world);
        $this->entityManager->flush();

        return $world;
    }
}

