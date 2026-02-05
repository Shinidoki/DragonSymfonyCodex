<?php

namespace App\Controller\Admin;

use App\Entity\CharacterEvent;
use App\Entity\NpcProfile;
use App\Game\Domain\Power\PowerLevelCalculator;
use App\Repository\CharacterEventRepository;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/characters', name: 'admin_character_')]
final class AdminCharacterController extends AbstractController
{
    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(
        int                      $id,
        Request $request,
        CharacterRepository      $characters,
        NpcProfileRepository     $npcProfiles,
        CharacterEventRepository $events,
        PowerLevelCalculator     $power,
    ): Response
    {
        $character = $characters->find($id);
        if ($character === null) {
            throw $this->createNotFoundException('Character not found.');
        }

        /** @var NpcProfile|null $profile */
        $profile   = $npcProfiles->findOneBy(['character' => $character]);
        $archetype = $profile?->getArchetype()->value;

        $jobLabel = $character->isEmployed()
            ? sprintf(
                '%s @ (%d,%d)',
                (string)$character->getEmploymentJobCode(),
                (int)$character->getEmploymentSettlementX(),
                (int)$character->getEmploymentSettlementY(),
            )
            : 'unemployed';

        $powerLevel = $power->calculate($character->getCoreAttributes());

        $page = (int)$request->query->get('page', 1);
        if ($page < 1) {
            $page = 1;
        }

        $perPage = (int)$request->query->get('perPage', 50);
        if ($perPage < 1) {
            $perPage = 1;
        }
        if ($perPage > 200) {
            $perPage = 200;
        }

        $totalEvents = $events->count(['character' => $character]);
        $totalPages  = max(1, (int)ceil($totalEvents / $perPage));
        if ($page > $totalPages) {
            $page = $totalPages;
        }

        /** @var list<CharacterEvent> $eventHistory */
        $eventHistory = $events->findBy(
            ['character' => $character],
            ['id' => 'DESC'],
            $perPage,
            ($page - 1) * $perPage,
        );

        return $this->render('admin/character/show.html.twig', [
            'character'    => $character,
            'powerLevel'   => $powerLevel,
            'jobLabel'     => $jobLabel,
            'archetype'    => $archetype,
            'eventHistory' => $eventHistory,
            'eventPage'       => $page,
            'eventPerPage'    => $perPage,
            'eventTotal'      => $totalEvents,
            'eventTotalPages' => $totalPages,
        ]);
    }
}
