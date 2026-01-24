<?php

namespace App\Entity;

use App\Repository\CharacterEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CharacterEventRepository::class)]
#[ORM\Table(name: 'game_character_event')]
#[ORM\Index(name: 'idx_character_event_world_day', columns: ['world_id', 'day'])]
#[ORM\Index(name: 'idx_character_event_world_type_day', columns: ['world_id', 'type', 'day'])]
#[ORM\Index(name: 'idx_character_event_character_day', columns: ['character_id', 'day'])]
class CharacterEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: World::class)]
    #[ORM\JoinColumn(nullable: false)]
    private World $world;

    #[ORM\ManyToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Character $character;

    #[ORM\Column(length: 128)]
    private string $type;

    #[ORM\Column]
    private int $day;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    /**
     * @var array<string,mixed>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $data;

    /**
     * @param array<string,mixed>|null $data
     */
    public function __construct(World $world, ?Character $character, string $type, int $day, ?array $data = null)
    {
        $type = trim($type);
        if ($type === '') {
            throw new \InvalidArgumentException('type must not be empty.');
        }
        if ($day < 0) {
            throw new \InvalidArgumentException('day must be >= 0.');
        }

        $this->world     = $world;
        $this->character = $character;
        $this->type      = $type;
        $this->day       = $day;
        $this->data      = $data;
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

    public function getCharacter(): ?Character
    {
        return $this->character;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDay(): int
    {
        return $this->day;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }
}

