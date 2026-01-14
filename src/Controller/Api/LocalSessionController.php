<?php

namespace App\Controller\Api;

use App\Entity\LocalActor;
use App\Entity\LocalCombat;
use App\Entity\LocalCombatant;
use App\Entity\LocalSession;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class LocalSessionController extends AbstractController
{
    #[Route('/api/local-sessions/{id}', name: 'api_local_session_show', methods: ['GET'])]
    public function show(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $session = $entityManager->find(LocalSession::class, $id);
        if (!$session instanceof LocalSession) {
            return $this->json(['error' => 'local_session_not_found'], status: 404);
        }

        /** @var list<LocalActor> $actors */
        $actors = $entityManager->getRepository(LocalActor::class)->findBy(['session' => $session], ['id' => 'ASC']);

        $combat            = $entityManager->getRepository(LocalCombat::class)->findOneBy(['session' => $session]);
        $combatantsPayload = [];
        if ($combat instanceof LocalCombat) {
            /** @var list<LocalCombatant> $combatants */
            $combatants        = $entityManager->getRepository(LocalCombatant::class)->findBy(['combat' => $combat], ['id' => 'ASC']);
            $combatantsPayload = array_map(static fn(LocalCombatant $combatant) => [
                'actorId'        => $combatant->getActorId(),
                'maxHp'          => $combatant->getMaxHp(),
                'currentHp'      => $combatant->getCurrentHp(),
                'maxKi'          => $combatant->getMaxKi(),
                'currentKi'      => $combatant->getCurrentKi(),
                'defeatedAtTick' => $combatant->getDefeatedAtTick(),
            ], $combatants);
        }

        return $this->json([
            'id'          => $session->getId(),
            'worldId'     => $session->getWorldId(),
            'characterId' => $session->getCharacterId(),
            'tileX'       => $session->getTileX(),
            'tileY'       => $session->getTileY(),
            'width'       => $session->getWidth(),
            'height'      => $session->getHeight(),
            'playerX'     => $session->getPlayerX(),
            'playerY'     => $session->getPlayerY(),
            'currentTick' => $session->getCurrentTick(),
            'status'      => $session->getStatus(),
            'actors'      => array_map(static fn(LocalActor $actor) => [
                'id'          => $actor->getId(),
                'characterId' => $actor->getCharacterId(),
                'role'        => $actor->getRole(),
                'x'           => $actor->getX(),
                'y'           => $actor->getY(),
                'turnMeter' => $actor->getTurnMeter(),
            ], $actors),
            'combat'      => $combat === null ? null : [
                'id'         => $combat->getId(),
                'status'     => $combat->getStatus(),
                'startedAt'  => $combat->getStartedAt()->format(\DateTimeInterface::ATOM),
                'endedAt'    => $combat->getEndedAt()?->format(\DateTimeInterface::ATOM),
                'combatants' => $combatantsPayload,
            ],
        ]);
    }
}
