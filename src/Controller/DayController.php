<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\User;
use App\Week\DayEditPanelLoader;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Enregistre les pointages saisis pour un jour. L'édition elle-même vit désormais
 * dans le panneau intégré à « Ma semaine » ({@see \App\Week\DayEditPanelLoader}) —
 * cette route ne fait plus que rediriger vers la semaine du jour concerné, pour ne
 * jamais faire perdre le contexte de la semaine qu'on modifiait (règle du
 * 29/07/2026, avant : on retombait systématiquement sur la semaine courante).
 *
 * Un pointage réel (collé depuis ADP) ne se touche jamais. Ce qu'on saisit ici
 * dépend de l'état de la journée : combler un créneau vide sur une journée déjà
 * réelle est une correction manuelle ({@see PunchEvent::manualCorrection()} — un
 * badge qu'ADP a manqué) ; saisir une journée entièrement vierge est une hypothèse
 * ({@see PunchEvent::provisional()}), remplacée dès que le réel arrivera.
 */
final class DayController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DayEditPanelLoader $panelLoader,
    ) {
    }

    #[Route('/jour/{date}', name: 'day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function show(string $date): Response
    {
        return $this->redirectToWeekWithDay($date);
    }

    #[Route('/jour/{date}', name: 'day_save', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['POST'])]
    public function save(string $date, Request $request, #[CurrentUser] User $user): Response
    {
        $day = new \DateTimeImmutable($date);
        $byRang = $this->panelLoader->punchesByRang($user, $day);
        // La nature de la saisie se décide sur l'état de la journée AVANT modification :
        // combler un trou sur du réel est une correction, pas une hypothèse.
        $dayHasReal = $this->hasAnyRealPunch($byRang);

        $toRemove = [];
        $toCreate = [];

        foreach (DayEditPanelLoader::FIELDS as $field => $rang) {
            $existing = $byRang[$rang] ?? null;
            if (null !== $existing && $existing->isProbative()) {
                continue; // un pointage réel ne se touche jamais
            }

            if (null !== $existing) {
                $toRemove[] = $existing;
            }

            $value = trim((string) $request->request->get($field, ''));
            if ('' === $value) {
                continue;
            }

            try {
                $time = Minutes::fromClock($value);
            } catch (\InvalidArgumentException) {
                continue; // horaire illisible : on ignore ce champ plutôt que d'échouer
            }

            $toCreate[] = $dayHasReal
                ? PunchEvent::manualCorrection($user, $day, $time, $rang)
                : PunchEvent::provisional($user, $day, $time, $rang);
        }

        if ([] !== $toRemove) {
            // Flush séparé : Doctrine insère avant de supprimer au sein d'un même
            // flush, ce qui violerait la contrainte d'unicité si le nouveau pointage
            // reprend le même créneau que celui qu'on remplace.
            foreach ($toRemove as $punch) {
                $this->entityManager->remove($punch);
            }
            $this->entityManager->flush();
        }

        foreach ($toCreate as $punch) {
            $this->entityManager->persist($punch);
        }
        $this->entityManager->flush();

        return $this->redirectToWeekWithDay($date);
    }

    /**
     * @param array<int, PunchEvent> $byRang
     */
    private function hasAnyRealPunch(array $byRang): bool
    {
        foreach ($byRang as $punch) {
            if ($punch->isProbative()) {
                return true;
            }
        }

        return false;
    }

    private function redirectToWeekWithDay(string $date): Response
    {
        $day = new \DateTimeImmutable($date);

        return $this->redirectToRoute('week', [
            'year' => (int) $day->format('o'),
            'week' => (int) $day->format('W'),
            'jour' => $date,
        ]);
    }
}
