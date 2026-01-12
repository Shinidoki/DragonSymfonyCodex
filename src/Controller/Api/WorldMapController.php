<?php

namespace App\Controller\Api;

use App\Entity\World;
use App\Repository\WorldMapTileRepository;
use App\Repository\WorldRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class WorldMapController extends AbstractController
{
    #[Route('/api/worlds/{id}/tiles', name: 'api_world_tile_show', methods: ['GET'])]
    public function showTile(int $id, Request $request, WorldRepository $worlds, WorldMapTileRepository $tiles): JsonResponse
    {
        $world = $worlds->find($id);
        if (!$world instanceof World) {
            return $this->json(['error' => 'world_not_found'], status: 404);
        }

        $x = $request->query->getInt('x', -1);
        $y = $request->query->getInt('y', -1);

        if ($x < 0 || $y < 0) {
            return $this->json(['error' => 'invalid_coordinates'], status: 400);
        }

        $tile = $tiles->findOneBy(['world' => $world, 'x' => $x, 'y' => $y]);
        if ($tile === null) {
            return $this->json(['error' => 'tile_not_found'], status: 404);
        }

        return $this->json([
            'worldId'       => $world->getId(),
            'x'             => $tile->getX(),
            'y'             => $tile->getY(),
            'biome'         => $tile->getBiome()->value,
            'hasSettlement' => $tile->hasSettlement(),
        ]);
    }
}

