<?php

namespace App\Controller\Api;

use App\Repository\CharacterRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class CharacterController extends AbstractController
{
    #[Route('/api/characters/{id}', name: 'api_character_show', methods: ['GET'])]
    public function show(int $id, CharacterRepository $characters): JsonResponse
    {
        $character = $characters->find($id);
        if ($character === null) {
            return $this->json(['error' => 'character_not_found'], status: 404);
        }

        return $this->json([
            'id'           => $character->getId(),
            'worldId'      => $character->getWorld()->getId(),
            'name'         => $character->getName(),
            'race'         => $character->getRace()->value,
            'ageDays'      => $character->getAgeDays(),
            'strength'     => $character->getStrength(),
            'speed'        => $character->getSpeed(),
            'endurance'    => $character->getEndurance(),
            'durability'   => $character->getDurability(),
            'kiCapacity'   => $character->getKiCapacity(),
            'kiControl'    => $character->getKiControl(),
            'kiRecovery'   => $character->getKiRecovery(),
            'focus'        => $character->getFocus(),
            'discipline'   => $character->getDiscipline(),
            'adaptability' => $character->getAdaptability(),
            'createdAt'    => $character->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}

