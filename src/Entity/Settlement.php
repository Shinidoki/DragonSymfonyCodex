<?php

namespace App\Entity;

use App\Repository\SettlementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettlementRepository::class)]
#[ORM\Table(name: 'game_settlement')]
#[ORM\UniqueConstraint(name: 'uniq_settlement_world_xy', columns: ['world_id', 'x', 'y'])]
class Settlement
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

    #[ORM\Column(options: ['default' => 50])]
    private int $prosperity = 50;

    #[ORM\Column(options: ['default' => 0])]
    private int $treasury = 0;

    #[ORM\Column(options: ['default' => 0])]
    private int $fame = 0;

    #[ORM\Column(options: ['default' => -1])]
    private int $lastSimDayApplied = -1;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(World $world, int $x, int $y)
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('Settlement coordinates must be >= 0.');
        }

        $this->world     = $world;
        $this->x         = $x;
        $this->y         = $y;
        $this->createdAt = new \DateTimeImmutable();
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

    public function getProsperity(): int
    {
        return $this->prosperity;
    }

    public function setProsperity(int $prosperity): void
    {
        if ($prosperity < 0) {
            throw new \InvalidArgumentException('prosperity must be >= 0.');
        }

        $this->prosperity = $prosperity;
    }

    public function getTreasury(): int
    {
        return $this->treasury;
    }

    public function addToTreasury(int $amount): void
    {
        $next = $this->treasury + $amount;
        if ($next < 0) {
            $next = 0;
        }

        $this->treasury = $next;
    }

    public function getFame(): int
    {
        return $this->fame;
    }

    public function addFame(int $amount): void
    {
        $next = $this->fame + $amount;
        if ($next < 0) {
            $next = 0;
        }

        $this->fame = $next;
    }

    public function getLastSimDayApplied(): int
    {
        return $this->lastSimDayApplied;
    }

    public function setLastSimDayApplied(int $day): void
    {
        if ($day < -1) {
            throw new \InvalidArgumentException('day must be >= -1.');
        }

        $this->lastSimDayApplied = $day;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

