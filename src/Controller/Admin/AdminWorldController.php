<?php

namespace App\Controller\Admin;

use App\Entity\Character;
use App\Entity\NpcProfile;
use App\Form\AdvanceSimulationType;
use App\Form\CreateWorldType;
use App\Game\Application\Simulation\AdvanceDayHandler;
use App\Game\Application\World\CreateWorldHandler;
use App\Game\Domain\Power\PowerLevelCalculator;
use App\Repository\CharacterRepository;
use App\Repository\NpcProfileRepository;
use App\Repository\WorldRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/worlds', name: 'admin_world_')]
final class AdminWorldController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(WorldRepository $worlds): Response
    {
        $createForm = $this->createForm(CreateWorldType::class);

        return $this->render('admin/world/index.html.twig', [
            'worlds'     => $worlds->findBy([], ['id' => 'DESC']),
            'createForm' => $createForm->createView(),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request, CreateWorldHandler $handler): Response
    {
        $form = $this->createForm(CreateWorldType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'World could not be created. Please check the form.');
            return $this->redirectToRoute('admin_world_index');
        }

        $seed  = (string)$form->get('seed')->getData();
        $world = $handler->create($seed);

        $this->addFlash('success', sprintf('Created world #%d (seed: %s).', (int)$world->getId(), $world->getSeed()));

        return $this->redirectToRoute('admin_world_show', ['id' => (int)$world->getId()]);
    }

    #[Route('/{id}', name: 'show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(
        int                  $id,
        Request              $request,
        WorldRepository      $worlds,
        CharacterRepository  $characters,
        NpcProfileRepository $npcProfiles,
        PowerLevelCalculator $power,
    ): Response
    {
        $world = $worlds->find($id);
        if ($world === null) {
            throw $this->createNotFoundException('World not found.');
        }

        $sort = (string)$request->query->get('sort', 'id');
        $dir  = strtolower((string)$request->query->get('dir', 'asc'));
        if (!in_array($dir, ['asc', 'desc'], true)) {
            $dir = 'asc';
        }

        $allowedSorts = [
            'id',
            'name',
            'race',
            'tileX',
            'tileY',
            'money',
            'powerlevel',
            'strength',
            'kiControl',
            'job',
            'archetype',
        ];
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = 'id';
        }

        $advanceForm = $this->createForm(AdvanceSimulationType::class);

        /** @var list<Character> $characterList */
        $characterList = $characters->findBy(['world' => $world], ['id' => 'ASC']);

        $powerLevels = [];
        $jobLabels   = [];
        foreach ($characterList as $c) {
            $characterId               = (int)$c->getId();
            $powerLevels[$characterId] = $power->calculate($c->getCoreAttributes());

            $jobLabels[$characterId] = $c->isEmployed()
                ? sprintf(
                    '%s @ (%d,%d)',
                    (string)$c->getEmploymentJobCode(),
                    (int)$c->getEmploymentSettlementX(),
                    (int)$c->getEmploymentSettlementY(),
                )
                : 'unemployed';
        }

        /** @var list<NpcProfile> $profiles */
        $profiles   = $npcProfiles->findByWorld($world);
        $archetypes = [];
        foreach ($profiles as $profile) {
            $archetypes[(int)$profile->getCharacter()->getId()] = $profile->getArchetype()->value;
        }

        usort($characterList, function (Character $a, Character $b) use ($sort, $dir, $powerLevels): int {
            $cmp = match ($sort) {
                'id' => ((int)$a->getId()) <=> ((int)$b->getId()),
                'name' => strcasecmp($a->getName(), $b->getName()),
                'race' => strcmp($a->getRace()->value, $b->getRace()->value),
                'tileX' => $a->getTileX() <=> $b->getTileX(),
                'tileY' => $a->getTileY() <=> $b->getTileY(),
                'money' => $a->getMoney() <=> $b->getMoney(),
                'strength' => $a->getStrength() <=> $b->getStrength(),
                'kiControl' => $a->getKiControl() <=> $b->getKiControl(),
                'powerlevel' => ($powerLevels[(int)$a->getId()] ?? 0) <=> ($powerLevels[(int)$b->getId()] ?? 0),
                default => ((int)$a->getId()) <=> ((int)$b->getId()),
            };

            if ($cmp === 0) {
                $cmp = ((int)$a->getId()) <=> ((int)$b->getId());
            }

            return $dir === 'desc' ? -$cmp : $cmp;
        });

        if (in_array($sort, ['job', 'archetype'], true)) {
            usort($characterList, function (Character $a, Character $b) use ($sort, $dir, $jobLabels, $archetypes): int {
                $aId = (int)$a->getId();
                $bId = (int)$b->getId();

                $cmp = match ($sort) {
                    'job' => strcasecmp($jobLabels[$aId] ?? '', $jobLabels[$bId] ?? ''),
                    'archetype' => strcmp($archetypes[$aId] ?? '', $archetypes[$bId] ?? ''),
                    default => 0,
                };

                if ($cmp === 0) {
                    $cmp = $aId <=> $bId;
                }

                return $dir === 'desc' ? -$cmp : $cmp;
            });
        }

        return $this->render('admin/world/show.html.twig', [
            'world'       => $world,
            'characters'  => $characterList,
            'advanceForm' => $advanceForm->createView(),
            'powerLevels' => $powerLevels,
            'jobLabels'   => $jobLabels,
            'archetypes'  => $archetypes,
            'sort'        => $sort,
            'dir'         => $dir,
        ]);
    }

    #[Route('/{id}/advance', name: 'advance', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function advance(int $id, Request $request, AdvanceDayHandler $handler): Response
    {
        $form = $this->createForm(AdvanceSimulationType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Simulation could not be advanced. Please check the form.');
            return $this->redirectToRoute('admin_world_show', ['id' => $id]);
        }

        $days   = (int)$form->get('days')->getData();
        $result = $handler->advance($id, $days);

        $this->addFlash('success', sprintf(
            'World #%d advanced by %d day(s) to day %d.',
            (int)$result->world->getId(),
            $result->daysAdvanced,
            $result->world->getCurrentDay(),
        ));

        return $this->redirectToRoute('admin_world_show', ['id' => (int)$result->world->getId()]);
    }
}
