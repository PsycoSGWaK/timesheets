<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Repository\EmployerReadingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Retire le(s) relevé(s) ADP d'un jour, depuis l'écran « Ma semaine » — utile
 * quand un import s'est trompé de journée. Ne touche jamais aux pointages : la
 * colonne ADP redevient simplement « — », le réel (colonne Nous) reste intact.
 */
final class EmployerReadingController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly EmployerReadingRepository $readings,
    ) {
    }

    #[Route('/semaine/adp/supprimer', name: 'employer_reading_remove', methods: ['POST'])]
    public function remove(Request $request, #[CurrentUser] User $user): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        foreach ($this->readings->findByDate($user, $date) as $reading) {
            $this->entityManager->remove($reading);
        }
        $this->entityManager->flush();

        return $this->redirectToRoute('week', [
            'year' => (int) $date->format('o'),
            'week' => (int) $date->format('W'),
        ]);
    }
}
