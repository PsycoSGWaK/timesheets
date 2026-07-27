<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Projection\LeaveEstimate;
use App\Domain\Projection\LeaveTimeCalculator;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\PunchEventRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * L'écran d'un jour : saisir ou corriger ses pointages, avec la projection
 * « quand partir » intégrée dès que matin/midi/après-midi sont connus
 * (spec §4.6 — l'usage principal de l'application, jusqu'ici sans aucune IHM).
 *
 * Un pointage réel (collé depuis ADP) ne se touche jamais. Ce qu'on saisit ici
 * dépend de l'état de la journée : combler un créneau vide sur une journée déjà
 * réelle est une correction manuelle ({@see PunchEvent::manualCorrection()} — un
 * badge qu'ADP a manqué) ; saisir une journée entièrement vierge est une hypothèse
 * ({@see PunchEvent::provisional()}), remplacée dès que le réel arrivera.
 */
final class DayController extends AbstractController
{
    /** Champ du formulaire => rang du pointage (spec §1.1 : Matin/Midi/Après-Midi/Soir). */
    private const FIELDS = ['matin' => 1, 'midi' => 2, 'apres_midi' => 3, 'soir' => 4];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PunchEventRepository $punches,
        private readonly SettingsRepository $settingsRepository,
        private readonly LeaveTimeCalculator $leaveTimeCalculator,
    ) {
    }

    #[Route('/jour/{date}', name: 'day', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['GET'])]
    public function show(string $date, #[CurrentUser] User $user): Response
    {
        $day = new \DateTimeImmutable($date);
        $byRang = $this->punchesByRang($user, $day);

        return $this->render('day/index.html.twig', [
            'date' => $day,
            'slots' => $this->slots($byRang),
            'estimate' => $this->estimate($byRang, $this->settingsRepository->forUser($user)),
        ]);
    }

    #[Route('/jour/{date}', name: 'day_save', requirements: ['date' => '\d{4}-\d{2}-\d{2}'], methods: ['POST'])]
    public function save(string $date, Request $request, #[CurrentUser] User $user): Response
    {
        $day = new \DateTimeImmutable($date);
        $byRang = $this->punchesByRang($user, $day);
        // La nature de la saisie se décide sur l'état de la journée AVANT modification :
        // combler un trou sur du réel est une correction, pas une hypothèse.
        $dayHasReal = $this->hasAnyRealPunch($byRang);

        $toRemove = [];
        $toCreate = [];

        foreach (self::FIELDS as $field => $rang) {
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

        return $this->redirectToRoute('day', ['date' => $date]);
    }

    /**
     * @return array<int, PunchEvent> le pointage de chaque rang occupé, 1 à 4
     */
    private function punchesByRang(User $user, \DateTimeImmutable $day): array
    {
        $byRang = [];
        foreach ($this->punches->findByDates($user, [$day]) as $punch) {
            $byRang[$punch->rang()] = $punch;
        }

        return $byRang;
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

    /**
     * @param array<int, PunchEvent> $byRang
     *
     * @return list<array{field: string, value: string, readonly: bool}>
     */
    private function slots(array $byRang): array
    {
        $slots = [];
        foreach (self::FIELDS as $field => $rang) {
            $punch = $byRang[$rang] ?? null;
            $slots[] = [
                'field' => $field,
                'value' => $punch?->time()->toClock() ?? '',
                'readonly' => $punch?->isProbative() ?? false,
            ];
        }

        return $slots;
    }

    /**
     * @param array<int, PunchEvent> $byRang
     */
    private function estimate(array $byRang, Settings $settings): ?LeaveEstimate
    {
        if (!isset($byRang[1], $byRang[2], $byRang[3])) {
            return null;
        }

        return $this->leaveTimeCalculator->estimate(
            $byRang[1]->time(),
            $byRang[2]->time(),
            $byRang[3]->time(),
            $settings,
        );
    }
}
