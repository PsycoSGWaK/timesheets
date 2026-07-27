<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayPortion;
use App\Entity\DayEvent;
use App\Entity\User;
use App\Repository\DayEventRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Déclare ou retire l'événement d'un jour (CP, CA, RTT, JF, TT — spec §2), depuis
 * l'écran « Ma semaine ». Un seul événement par jour : en déclarer un nouveau
 * remplace le précédent plutôt que d'échouer sur la contrainte d'unicité.
 */
final class DayEventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DayEventRepository $events,
    ) {
    }

    #[Route('/semaine/evenement', name: 'day_event_declare', methods: ['POST'])]
    public function declare(Request $request, #[CurrentUser] User $user): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));
        $code = DayEventCode::from((string) $request->request->get('code'));
        $portion = DayPortion::from((string) $request->request->get('portion', DayPortion::Full->value));

        $existing = $this->events->findOneByDate($user, $date);
        if (null !== $existing) {
            // Flush separe : au sein d'un meme flush, Doctrine insere avant de
            // supprimer, ce qui violerait la contrainte d'unicite (user, date)
            // tant que l'ancien evenement n'est pas encore efface.
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $this->entityManager->persist(DayEvent::declare($user, $date, $code, $portion));
        $this->entityManager->flush();

        return $this->redirectToWeekOf($date);
    }

    #[Route('/semaine/evenement/supprimer', name: 'day_event_remove', methods: ['POST'])]
    public function remove(Request $request, #[CurrentUser] User $user): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        $existing = $this->events->findOneByDate($user, $date);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        return $this->redirectToWeekOf($date);
    }

    private function redirectToWeekOf(\DateTimeImmutable $date): Response
    {
        return $this->redirectToRoute('week', [
            'year' => (int) $date->format('o'),
            'week' => (int) $date->format('W'),
        ]);
    }
}
