<?php

namespace App\Entity;

use App\Repository\TournamentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TournamentRepository::class)]
#[ORM\Table(name: 'game_tournament')]
#[ORM\UniqueConstraint(name: 'uniq_tournament_request_event', columns: ['request_event_id'])]
#[ORM\Index(name: 'idx_tournament_world_resolve', columns: ['world_id', 'resolve_day', 'status'])]
class Tournament
{
    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_RESOLVED  = 'resolved';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: World::class)]
    #[ORM\JoinColumn(nullable: false)]
    private World $world;

    #[ORM\ManyToOne(targetEntity: Settlement::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Settlement $settlement;

    #[ORM\Column]
    private int $announceDay;

    #[ORM\Column]
    private int $resolveDay;

    #[ORM\Column]
    private int $spend;

    #[ORM\Column]
    private int $prizePool;

    #[ORM\Column]
    private int $radius;

    #[ORM\Column(length: 16)]
    private string $status = self::STATUS_SCHEDULED;

    #[ORM\Column(nullable: true)]
    private ?int $requestEventId = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(World $world, Settlement $settlement, int $announceDay, int $resolveDay, int $spend, int $prizePool, int $radius, ?int $requestEventId)
    {
        if ($announceDay < 0 || $resolveDay < 0 || $resolveDay < $announceDay) {
            throw new \InvalidArgumentException('Invalid tournament days.');
        }
        if ($spend < 0 || $prizePool < 0 || $prizePool > $spend) {
            throw new \InvalidArgumentException('Invalid spend/prize pool.');
        }
        if ($radius < 0) {
            throw new \InvalidArgumentException('radius must be >= 0.');
        }
        if ($requestEventId !== null && $requestEventId <= 0) {
            throw new \InvalidArgumentException('requestEventId must be positive when provided.');
        }

        $this->world          = $world;
        $this->settlement     = $settlement;
        $this->announceDay    = $announceDay;
        $this->resolveDay     = $resolveDay;
        $this->spend          = $spend;
        $this->prizePool      = $prizePool;
        $this->radius         = $radius;
        $this->requestEventId = $requestEventId;
        $this->createdAt      = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getWorld(): World
    {
        return $this->world;
    }

    public function getSettlement(): Settlement
    {
        return $this->settlement;
    }

    public function getAnnounceDay(): int
    {
        return $this->announceDay;
    }

    public function getResolveDay(): int
    {
        return $this->resolveDay;
    }

    public function getSpend(): int
    {
        return $this->spend;
    }

    public function getPrizePool(): int
    {
        return $this->prizePool;
    }

    public function getRadius(): int
    {
        return $this->radius;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function markResolved(): void
    {
        $this->status = self::STATUS_RESOLVED;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
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

