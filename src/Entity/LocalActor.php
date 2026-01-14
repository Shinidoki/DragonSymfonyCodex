<?php

namespace App\Entity;

use App\Repository\LocalActorRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalActorRepository::class)]
#[ORM\Table(name: 'local_actor')]
#[ORM\UniqueConstraint(name: 'uniq_local_actor_session_character', columns: ['session_id', 'character_id'])]
class LocalActor
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocalSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocalSession $session;

    #[ORM\Column]
    private int $characterId;

    #[ORM\Column(length: 16)]
    private string $role;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\Column(options: ['default' => 0])]
    private int $turnMeter = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $chargingTechniqueCode = null;

    #[ORM\Column(options: ['default' => 0])]
    private int $chargingTicksRemaining = 0;

    #[ORM\Column(nullable: true)]
    private ?int $chargingTargetActorId = null;

    public function __construct(LocalSession $session, int $characterId, string $role, int $x, int $y)
    {
        if ($characterId <= 0) {
            throw new \InvalidArgumentException('characterId must be positive.');
        }
        $role = trim($role);
        if ($role === '') {
            throw new \InvalidArgumentException('role must not be empty.');
        }
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('coordinates must be >= 0.');
        }

        $this->session     = $session;
        $this->characterId = $characterId;
        $this->role        = $role;
        $this->x           = $x;
        $this->y           = $y;
        $this->createdAt   = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): LocalSession
    {
        return $this->session;
    }

    public function getCharacterId(): int
    {
        return $this->characterId;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }
    public function getTurnMeter(): int
    {
        return $this->turnMeter;
    }

    public function setTurnMeter(int $turnMeter): void
    {
        if ($turnMeter < 0) {
            throw new \InvalidArgumentException('turnMeter must be >= 0.');
        }

        $this->turnMeter = $turnMeter;
    }

    public function setPosition(int $x, int $y): void
    {
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('coordinates must be >= 0.');
        }

        $this->x = $x;
        $this->y = $y;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function isCharging(): bool
    {
        return $this->chargingTechniqueCode !== null && $this->chargingTicksRemaining > 0;
    }

    public function getChargingTechniqueCode(): ?string
    {
        return $this->chargingTechniqueCode;
    }

    public function getChargingTicksRemaining(): int
    {
        return $this->chargingTicksRemaining;
    }

    public function getChargingTargetActorId(): ?int
    {
        return $this->chargingTargetActorId;
    }

    public function startCharging(string $techniqueCode, int $ticksRemaining, int $targetActorId): void
    {
        $techniqueCode = strtolower(trim($techniqueCode));
        if ($techniqueCode === '') {
            throw new \InvalidArgumentException('techniqueCode must not be empty.');
        }
        if ($ticksRemaining < 0) {
            throw new \InvalidArgumentException('ticksRemaining must be >= 0.');
        }
        if ($targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be positive.');
        }

        $this->chargingTechniqueCode  = $techniqueCode;
        $this->chargingTicksRemaining = $ticksRemaining;
        $this->chargingTargetActorId  = $targetActorId;
    }

    public function decrementChargingTick(): void
    {
        if ($this->chargingTicksRemaining <= 0) {
            return;
        }

        $this->chargingTicksRemaining--;
    }

    public function clearCharging(): void
    {
        $this->chargingTechniqueCode  = null;
        $this->chargingTicksRemaining = 0;
        $this->chargingTargetActorId  = null;
    }
}
