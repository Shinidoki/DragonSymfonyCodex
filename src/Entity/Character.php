<?php

namespace App\Entity;

use App\Game\Domain\Race;
use App\Game\Domain\Stats\CoreAttributes;
use App\Game\Domain\Transformations\Transformation;
use App\Game\Domain\Transformations\TransformationState;
use App\Repository\CharacterRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterRepository::class)]
#[ORM\Table(name: 'game_character')]
class Character
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: World::class, inversedBy: 'characters')]
    #[ORM\JoinColumn(nullable: false)]
    private World $world;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(enumType: Race::class)]
    private Race $race;

    #[ORM\Column]
    private int $ageDays = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $tileX = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $tileY = 0;

    #[ORM\Column(nullable: true)]
    private ?int $targetTileX = null;

    #[ORM\Column(nullable: true)]
    private ?int $targetTileY = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private int $strength = 1;

    #[ORM\Column]
    private int $speed = 1;

    #[ORM\Column]
    private int $endurance = 1;

    #[ORM\Column]
    private int $durability = 1;

    #[ORM\Column]
    private int $kiCapacity = 1;

    #[ORM\Column]
    private int $kiControl = 1;

    #[ORM\Column]
    private int $kiRecovery = 1;

    #[ORM\Column]
    private int $focus = 1;

    #[ORM\Column]
    private int $discipline = 1;

    #[ORM\Column]
    private int $adaptability = 1;

    #[ORM\Column(enumType: Transformation::class, nullable: true)]
    private ?Transformation $transformationActive = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $transformationActiveTicks = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $transformationExhaustionDaysRemaining = 0;

    public function __construct(World $world, string $name, Race $race)
    {
        $this->world     = $world;
        $this->name      = $name;
        $this->race      = $race;
        $this->createdAt = new \DateTimeImmutable();

        $world->addCharacter($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getRace(): Race
    {
        return $this->race;
    }

    public function getAgeDays(): int
    {
        return $this->ageDays;
    }

    public function getTileX(): int
    {
        return $this->tileX;
    }

    public function getTileY(): int
    {
        return $this->tileY;
    }

    public function setTilePosition(int $x, int $y): void
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Tile coordinates must be >= 0.');
        }

        $this->tileX = $x;
        $this->tileY = $y;
    }

    public function hasTravelTarget(): bool
    {
        return $this->targetTileX !== null && $this->targetTileY !== null;
    }

    public function getTargetTileX(): ?int
    {
        return $this->targetTileX;
    }

    public function getTargetTileY(): ?int
    {
        return $this->targetTileY;
    }

    public function setTravelTarget(int $x, int $y): void
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Travel target coordinates must be >= 0.');
        }

        $this->targetTileX = $x;
        $this->targetTileY = $y;
    }

    public function clearTravelTarget(): void
    {
        $this->targetTileX = null;
        $this->targetTileY = null;
    }

    public function advanceDays(int $days): void
    {
        if ($days < 0) {
            throw new \InvalidArgumentException('Days must be >= 0.');
        }

        $this->ageDays += $days;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getCoreAttributes(): CoreAttributes
    {
        return new CoreAttributes(
            strength: $this->strength,
            speed: $this->speed,
            endurance: $this->endurance,
            durability: $this->durability,
            kiCapacity: $this->kiCapacity,
            kiControl: $this->kiControl,
            kiRecovery: $this->kiRecovery,
            focus: $this->focus,
            discipline: $this->discipline,
            adaptability: $this->adaptability,
        );
    }

    public function applyCoreAttributes(CoreAttributes $attributes): void
    {
        $this->strength     = $attributes->strength;
        $this->speed        = $attributes->speed;
        $this->endurance    = $attributes->endurance;
        $this->durability   = $attributes->durability;
        $this->kiCapacity   = $attributes->kiCapacity;
        $this->kiControl    = $attributes->kiControl;
        $this->kiRecovery   = $attributes->kiRecovery;
        $this->focus        = $attributes->focus;
        $this->discipline   = $attributes->discipline;
        $this->adaptability = $attributes->adaptability;
    }

    public function getTransformationState(): TransformationState
    {
        return new TransformationState(
            active: $this->transformationActive,
            activeTicks: $this->transformationActiveTicks,
            exhaustionDaysRemaining: $this->transformationExhaustionDaysRemaining,
        );
    }

    public function setTransformationState(TransformationState $state): void
    {
        if ($state->activeTicks < 0) {
            throw new \InvalidArgumentException('activeTicks must be >= 0.');
        }
        if ($state->exhaustionDaysRemaining < 0) {
            throw new \InvalidArgumentException('exhaustionDaysRemaining must be >= 0.');
        }

        $this->transformationActive                   = $state->active;
        $this->transformationActiveTicks              = $state->activeTicks;
        $this->transformationExhaustionDaysRemaining  = $state->exhaustionDaysRemaining;
    }

    public function getStrength(): int
    {
        return $this->strength;
    }

    public function setStrength(int $strength): void
    {
        $this->strength = $strength;
    }

    public function getSpeed(): int
    {
        return $this->speed;
    }

    public function setSpeed(int $speed): void
    {
        $this->speed = $speed;
    }

    public function getEndurance(): int
    {
        return $this->endurance;
    }

    public function setEndurance(int $endurance): void
    {
        $this->endurance = $endurance;
    }

    public function getDurability(): int
    {
        return $this->durability;
    }

    public function setDurability(int $durability): void
    {
        $this->durability = $durability;
    }

    public function getKiCapacity(): int
    {
        return $this->kiCapacity;
    }

    public function setKiCapacity(int $kiCapacity): void
    {
        $this->kiCapacity = $kiCapacity;
    }

    public function getKiControl(): int
    {
        return $this->kiControl;
    }

    public function setKiControl(int $kiControl): void
    {
        $this->kiControl = $kiControl;
    }

    public function getKiRecovery(): int
    {
        return $this->kiRecovery;
    }

    public function setKiRecovery(int $kiRecovery): void
    {
        $this->kiRecovery = $kiRecovery;
    }

    public function getFocus(): int
    {
        return $this->focus;
    }

    public function setFocus(int $focus): void
    {
        $this->focus = $focus;
    }

    public function getDiscipline(): int
    {
        return $this->discipline;
    }

    public function setDiscipline(int $discipline): void
    {
        $this->discipline = $discipline;
    }

    public function getAdaptability(): int
    {
        return $this->adaptability;
    }

    public function setAdaptability(int $adaptability): void
    {
        $this->adaptability = $adaptability;
    }
}
