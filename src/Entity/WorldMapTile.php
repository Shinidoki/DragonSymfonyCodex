<?php

namespace App\Entity;

use App\Game\Domain\Map\Biome;
use App\Repository\WorldMapTileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorldMapTileRepository::class)]
#[ORM\Table(name: 'world_map_tile')]
#[ORM\UniqueConstraint(name: 'uniq_world_xy', columns: ['world_id', 'x', 'y'])]
class WorldMapTile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: World::class)]
    #[ORM\JoinColumn(nullable: false)]
    private World $world;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\Column(enumType: Biome::class)]
    private Biome $biome;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasSettlement = false;

    #[ORM\Column(options: ['default' => false])]
    private bool $hasDojo = false;

    public function __construct(World $world, int $x, int $y, Biome $biome)
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Tile coordinates must be >= 0.');
        }

        $this->world = $world;
        $this->x     = $x;
        $this->y     = $y;
        $this->biome = $biome;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getBiome(): Biome
    {
        return $this->biome;
    }

    public function setBiome(Biome $biome): void
    {
        $this->biome = $biome;
    }

    public function hasSettlement(): bool
    {
        return $this->hasSettlement;
    }

    public function setHasSettlement(bool $hasSettlement): void
    {
        $this->hasSettlement = $hasSettlement;
    }

    public function hasDojo(): bool
    {
        return $this->hasDojo;
    }

    public function setHasDojo(bool $hasDojo): void
    {
        $this->hasDojo = $hasDojo;
    }
}
