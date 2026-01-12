<?php

namespace App\Entity;

use App\Repository\WorldRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WorldRepository::class)]
class World
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 128)]
    private string $seed;

    #[ORM\Column(length: 64)]
    private string $planetName = 'Earth';

    #[ORM\Column(options: ['default' => 0])]
    private int $width = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $height = 0;

    #[ORM\Column]
    private int $currentDay = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, Character>
     */
    #[ORM\OneToMany(mappedBy: 'world', targetEntity: Character::class, cascade: ['persist'], orphanRemoval: true)]
    private Collection $characters;

    public function __construct(string $seed)
    {
        $this->seed       = $seed;
        $this->createdAt  = new \DateTimeImmutable();
        $this->characters = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSeed(): string
    {
        return $this->seed;
    }

    public function getPlanetName(): string
    {
        return $this->planetName;
    }

    public function setPlanetName(string $planetName): void
    {
        $planetName = trim($planetName);
        if ($planetName === '') {
            throw new \InvalidArgumentException('Planet name must not be empty.');
        }

        $this->planetName = $planetName;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }

    public function setMapSize(int $width, int $height): void
    {
        if ($width < 0 || $height < 0) {
            throw new \InvalidArgumentException('Map size must be >= 0.');
        }

        $this->width  = $width;
        $this->height = $height;
    }

    public function getCurrentDay(): int
    {
        return $this->currentDay;
    }

    public function advanceDays(int $days): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $this->currentDay += $days;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Character>
     */
    public function getCharacters(): Collection
    {
        return $this->characters;
    }

    public function addCharacter(Character $character): void
    {
        if ($this->characters->contains($character)) {
            return;
        }

        $this->characters->add($character);
    }
}
