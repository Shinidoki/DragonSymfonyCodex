<?php

namespace App\Controller\Api;

use App\Repository\WorldRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class WorldController extends AbstractController
{
    #[Route('/api/worlds/{id}', name: 'api_world_show', methods: ['GET'])]
    public function show(int $id, WorldRepository $worlds): JsonResponse
    {
        $world = $worlds->find($id);
        if ($world === null) {
            return $this->json(['error' => 'world_not_found'], status: 404);
        }

        return $this->json([
            'id'         => $world->getId(),
            'seed'       => $world->getSeed(),
            'currentDay' => $world->getCurrentDay(),
            'createdAt'  => $world->getCreatedAt()->format(\DateTimeInterface::ATOM),
        ]);
    }
}

