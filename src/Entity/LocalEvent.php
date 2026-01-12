<?php

namespace App\Entity;

use App\Repository\LocalEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LocalEventRepository::class)]
#[ORM\Table(name: 'local_event')]
class LocalEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: LocalSession::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private LocalSession $session;

    #[ORM\Column]
    private int $tick;

    #[ORM\Column]
    private int $x;

    #[ORM\Column]
    private int $y;

    #[ORM\Column(type: Types::TEXT)]
    private string $message;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(LocalSession $session, int $tick, int $x, int $y, string $message)
    {
        if ($tick < 0) {
            throw new \InvalidArgumentException('tick must be >= 0.');
        }
        if ($x < 0 || $y < 0) {
            throw new \InvalidArgumentException('coordinates must be >= 0.');
        }
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('message must not be empty.');
        }

        $this->session   = $session;
        $this->tick      = $tick;
        $this->x         = $x;
        $this->y         = $y;
        $this->message   = $message;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSession(): LocalSession
    {
        return $this->session;
    }

    public function getTick(): int
    {
        return $this->tick;
    }

    public function getX(): int
    {
        return $this->x;
    }

    public function getY(): int
    {
        return $this->y;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}

