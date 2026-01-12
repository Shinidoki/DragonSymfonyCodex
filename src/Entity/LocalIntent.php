<?php

namespace App\Entity;

use App\Game\Domain\LocalNpc\IntentType;
use App\Repository\LocalIntentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalIntentRepository::class)]
#[ORM\Table(name: 'local_intent')]
class LocalIntent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocalActor::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocalActor $actor;

    #[ORM\Column(enumType: IntentType::class)]
    private IntentType $type;

    #[ORM\Column(nullable: true)]
    private ?int $targetActorId;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(LocalActor $actor, IntentType $type, ?int $targetActorId = null)
    {
        if ($targetActorId !== null && $targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be positive when provided.');
        }

        $this->actor         = $actor;
        $this->type          = $type;
        $this->targetActorId = $targetActorId;
        $this->createdAt     = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getActor(): LocalActor
    {
        return $this->actor;
    }

    public function getType(): IntentType
    {
        return $this->type;
    }

    public function setType(IntentType $type): void
    {
        $this->type = $type;
    }

    public function getTargetActorId(): ?int
    {
        return $this->targetActorId;
    }

    public function setTargetActorId(?int $targetActorId): void
    {
        if ($targetActorId !== null && $targetActorId <= 0) {
            throw new \InvalidArgumentException('targetActorId must be positive when provided.');
        }

        $this->targetActorId = $targetActorId;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

