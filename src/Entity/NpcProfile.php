<?php

namespace App\Entity;

use App\Game\Domain\Npc\NpcArchetype;
use App\Repository\NpcProfileRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NpcProfileRepository::class)]
#[ORM\Table(name: 'game_npc_profile')]
#[ORM\UniqueConstraint(name: 'uniq_npc_profile_character', columns: ['character_id'])]
class NpcProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Character::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Character $character;

    #[ORM\Column(enumType: NpcArchetype::class)]
    private NpcArchetype $archetype;

    #[ORM\Column(options: ['default' => 0])]
    private int $wanderSequence = 0;

    public function __construct(Character $character, NpcArchetype $archetype)
    {
        $this->character = $character;
        $this->archetype = $archetype;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCharacter(): Character
    {
        return $this->character;
    }

    public function getArchetype(): NpcArchetype
    {
        return $this->archetype;
    }

    public function getWanderSequence(): int
    {
        return $this->wanderSequence;
    }

    public function incrementWanderSequence(): void
    {
        $this->wanderSequence++;
    }
}

