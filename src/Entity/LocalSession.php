<?php

namespace App\Entity;

use App\Repository\LocalSessionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalSessionRepository::class)]
#[ORM\Table(name: 'local_session')]
class LocalSession
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private int $worldId;

    #[ORM\Column]
    private int $characterId;

    #[ORM\Column]
    private int $tileX;

    #[ORM\Column]
    private int $tileY;

    #[ORM\Column]
    private int $width;

    #[ORM\Column]
    private int $height;

    #[ORM\Column]
    private int $playerX;

    #[ORM\Column]
    private int $playerY;

    #[ORM\Column(options: ['default' => 0])]
    private int $currentTick = 0;

    #[ORM\Column(length: 16)]
    private string $status = 'active';

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        int $worldId,
        int $characterId,
        int $tileX,
        int $tileY,
        int $width,
        int $height,
        int $playerX,
        int $playerY,
    )
    {
        if ($worldId <= 0) {
            throw new \InvalidArgumentException('worldId must be positive.');
        }
        if ($characterId <= 0) {
            throw new \InvalidArgumentException('characterId must be positive.');
        }
        if ($tileX < 0 || $tileY < 0) {
            throw new \InvalidArgumentException('tile coordinates must be >= 0.');
        }
        if ($width <= 0 || $height <= 0) {
            throw new \InvalidArgumentException('width/height must be positive.');
        }
        if ($playerX < 0 || $playerY < 0) {
            throw new \InvalidArgumentException('player coordinates must be >= 0.');
        }

        $this->worldId     = $worldId;
        $this->characterId = $characterId;
        $this->tileX       = $tileX;
        $this->tileY       = $tileY;
        $this->width       = $width;
        $this->height      = $height;
        $this->playerX     = min($playerX, $width - 1);
        $this->playerY     = min($playerY, $height - 1);

        $now             = new \DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorldId(): int
    {
        return $this->worldId;
    }

    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    public function getTileX(): int
    {
        return $this->tileX;
    }

    public function getTileY(): int
    {
        return $this->tileY;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function getPlayerX(): int
    {
        return $this->playerX;
    }

    public function getPlayerY(): int
    {
        return $this->playerY;
    }

    public function setPlayerPosition(int $x, int $y): void
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('player coordinates must be >= 0.');
        }

        $this->playerX = min($x, $this->width - 1);
        $this->playerY = min($y, $this->height - 1);
        $this->touch();
    }

    public function getCurrentTick(): int
    {
        return $this->currentTick;
    }

    public function incrementTick(): void
    {
        $this->currentTick++;
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function suspend(): void
    {
        $this->status = 'suspended';
        $this->touch();
    }

    public function resume(): void
    {
        $this->status = 'active';
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

