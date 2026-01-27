<?php

namespace App\Entity;

use App\Repository\SettlementProjectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettlementProjectRepository::class)]
#[ORM\Table(name: 'game_settlement_project')]
#[ORM\UniqueConstraint(name: 'uniq_settlement_project_request_event', columns: ['request_event_id'])]
#[ORM\Index(name: 'idx_settlement_project_settlement_status', columns: ['settlement_id', 'status'])]
class SettlementProject
{
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELED  = 'canceled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Settlement::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Settlement $settlement;

    #[ORM\Column(length: 32)]
    private string $buildingCode;

    #[ORM\Column]
    private int $targetLevel;

    #[ORM\Column]
    private int $requiredWorkUnits;

    #[ORM\Column(options: ['default' => 0])]
    private int $progressWorkUnits = 0;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private int $startedDay;

    #[ORM\Column(options: ['default' => -1])]
    private int $lastSimDayApplied = -1;

    #[ORM\Column(nullable: true)]
    private ?int $requestEventId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Settlement $settlement,
        string     $buildingCode,
        int        $targetLevel,
        int        $requiredWorkUnits,
        int        $startedDay,
        ?int       $requestEventId,
    )
    {
        $buildingCode = strtolower(trim($buildingCode));
        if ($buildingCode === '') {
            throw new \InvalidArgumentException('buildingCode must not be empty.');
        }
        if ($targetLevel <= 0) {
            throw new \InvalidArgumentException('targetLevel must be positive.');
        }
        if ($requiredWorkUnits <= 0) {
            throw new \InvalidArgumentException('requiredWorkUnits must be positive.');
        }
        if ($startedDay < 0) {
            throw new \InvalidArgumentException('startedDay must be >= 0.');
        }
        if ($requestEventId !== null && $requestEventId <= 0) {
            throw new \InvalidArgumentException('requestEventId must be positive when provided.');
        }

        $this->settlement        = $settlement;
        $this->buildingCode      = $buildingCode;
        $this->targetLevel       = $targetLevel;
        $this->requiredWorkUnits = $requiredWorkUnits;
        $this->startedDay        = $startedDay;
        $this->requestEventId    = $requestEventId;
        $this->createdAt         = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSettlement(): Settlement
    {
        return $this->settlement;
    }

    public function getBuildingCode(): string
    {
        return $this->buildingCode;
    }

    public function getTargetLevel(): int
    {
        return $this->targetLevel;
    }

    public function getRequiredWorkUnits(): int
    {
        return $this->requiredWorkUnits;
    }

    public function getProgressWorkUnits(): int
    {
        return $this->progressWorkUnits;
    }

    public function addProgressWorkUnits(int $amount): void
    {
        if ($amount < 0) {
            throw new \InvalidArgumentException('amount must be >= 0.');
        }

        $this->progressWorkUnits += $amount;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function markCompleted(): void
    {
        $this->status = self::STATUS_COMPLETED;
    }

    public function markCanceled(): void
    {
        $this->status = self::STATUS_CANCELED;
    }

    public function getStartedDay(): int
    {
        return $this->startedDay;
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

    public function getRequestEventId(): ?int
    {
        return $this->requestEventId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
