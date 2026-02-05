<?php

namespace App\Controller\Admin;

use App\Repository\CharacterRepository;
use App\Repository\WorldRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class AdminDashboardController extends AbstractController
{
    #[Route('/admin', name: 'admin_dashboard', methods: ['GET'])]
    public function index(WorldRepository $worlds, CharacterRepository $characters): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'worldCount'     => $worlds->count([]),
            'characterCount' => $characters->count([]),
        ]);
    }
}

