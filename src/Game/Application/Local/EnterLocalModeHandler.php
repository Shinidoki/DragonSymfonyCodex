<?php

namespace App\Game\Application\Local;

use App\Entity\Character;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class EnterLocalModeHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    )
    {
    }

    public function enter(int $characterId, int $width = 8, int $height = 8): LocalSession
    {
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('width/height must be positive.');
        }

        /** @var EntityRepository<LocalSession> $sessionRepository */
        $sessionRepository = $this->entityManager->getRepository(LocalSession::class);

        $existing = $sessionRepository->findOneBy(['characterId' => $characterId, 'status' => 'active']);
        if ($existing instanceof LocalSession) {
            return $existing;
        }

        $character = $this->entityManager->find(Character::class, $characterId);
        if (!$character instanceof Character) {
            throw new \RuntimeException(sprintf('Character not found: %d', $characterId));
        }

        $playerX = intdiv($width, 2);
        $playerY = intdiv($height, 2);

        $session = new LocalSession(
            worldId: (int)$character->getWorld()->getId(),
            characterId: (int)$character->getId(),
            tileX: $character->getTileX(),
            tileY: $character->getTileY(),
            width: $width,
            height: $height,
            playerX: $playerX,
            playerY: $playerY,
        );

        $this->entityManager->persist($session);
        $this->entityManager->flush();

        return $session;
    }
}
