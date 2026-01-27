<?php

namespace App\Entity;

use App\Repository\SettlementBuildingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettlementBuildingRepository::class)]
#[ORM\Table(name: 'game_settlement_building')]
#[ORM\UniqueConstraint(name: 'uniq_settlement_building', columns: ['settlement_id', 'code'])]
#[ORM\Index(name: 'idx_settlement_building_settlement', columns: ['settlement_id'])]
class SettlementBuilding
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Settlement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Settlement $settlement;

    #[ORM\Column(length: 32)]
    private string $code;

    #[ORM\Column(options: ['default' => 0])]
    private int $level = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(Settlement $settlement, string $code, int $level = 0)
    {
        $code = strtolower(trim($code));
        if ($code === '') {
            throw new \InvalidArgumentException('code must not be empty.');
        }
        if ($level < 0) {
            throw new \InvalidArgumentException('level must be >= 0.');
        }

        $this->settlement = $settlement;
        $this->code       = $code;
        $this->level      = $level;
        $this->createdAt  = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettlement(): Settlement
    {
        return $this->settlement;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): void
    {
        if ($level < 0) {
            throw new \InvalidArgumentException('level must be >= 0.');
        }

        $this->level = $level;
    }

    public function incrementLevel(int $by = 1): void
    {
        if ($by <= 0) {
            throw new \InvalidArgumentException('by must be positive.');
        }

        $this->level += $by;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
