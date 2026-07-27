<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Projection\WeekProjectionCalculator;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\User;
use App\Repository\DayEventRepository;
use App\Repository\EmployerReadingRepository;
use App\Repository\PunchEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Affiche une semaine : notre recalcul face au relevé d'ADP, jour par jour, plus
 * l'agrégat hebdomadaire (total, RTT, heures supplémentaires) et les écarts.
 */
final class WeekController extends AbstractController
{
    public function __construct(
        private readonly PunchEventRepository $punches,
        private readonly EmployerReadingRepository $readings,
        private readonly DayEventRepository $events,
        private readonly WorkWeekAssembler $assembler,
        private readonly WeekProjectionCalculator $projectionCalculator,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/semaine', name: 'week_current', methods: ['GET'])]
    public function currentWeek(#[CurrentUser] User $user): Response
    {
        $today = $this->today();

        return $this->renderWeek($user, (int) $today->format('o'), (int) $today->format('W'));
    }

    #[Route('/semaine/{year}/{week}', name: 'week', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['GET'])]
    public function week(int $year, int $week, #[CurrentUser] User $user): Response
    {
        return $this->renderWeek($user, $year, $week);
    }

    /**
     * Cible du sélecteur natif <input type="week"> (spec §2, sélection de semaine
     * plus rapide qu'un défilement précédent/suivant). Une valeur illisible ramène
     * simplement à la semaine courante plutôt que d'échouer.
     */
    #[Route('/semaine/aller', name: 'week_goto', methods: ['GET'])]
    public function goto(Request $request): Response
    {
        $value = (string) $request->query->get('semaine', '');

        if (1 === preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches)) {
            return $this->redirectToRoute('week', ['year' => (int) $matches[1], 'week' => (int) $matches[2]]);
        }

        return $this->redirectToRoute('week_current');
    }

    private function renderWeek(User $user, int $year, int $week): Response
    {
        $monday = (new \DateTimeImmutable())->setISODate($year, $week)->setTime(0, 0, 0);

        $dates = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $dates[] = $monday->modify(sprintf('+%d days', $offset));
        }

        $today = $this->today();

        $workWeek = $this->assembler->assemble(
            $dates,
            $this->punches->findByDates($user, $dates),
            $this->readings->latestMinutesByDates($user, $dates),
            $this->events->findByDates($user, $dates),
            $today,
        );

        $projection = $this->projectionCalculator->project(
            $workWeek->weekFact()->workedMinutes(),
            $this->countRemainingWorkingDays($dates, $today),
        );

        $previous = $monday->modify('-7 days');
        $next = $monday->modify('+7 days');

        return $this->render('week/index.html.twig', [
            'workWeek' => $workWeek,
            'projection' => $projection,
            'week' => $week,
            'monday' => $monday,
            'sunday' => $monday->modify('+6 days'),
            'previous' => ['year' => (int) $previous->format('o'), 'week' => (int) $previous->format('W')],
            'next' => ['year' => (int) $next->format('o'), 'week' => (int) $next->format('W')],
            'pickerValue' => sprintf('%04d-W%02d', $year, $week),
        ]);
    }

    /**
     * « Aujourd'hui » à minuit, selon l'horloge injectée — jamais l'horloge système
     * directement, pour que la consolidation ADP (§4bis) reste testable de façon
     * déterministe.
     */
    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0, 0);
    }

    /**
     * Jours ouvrés (lun-ven) de la semaine encore à venir, aujourd'hui inclus : ceux
     * sur lesquels la répartition du temps restant a du sens.
     *
     * @param list<\DateTimeImmutable> $dates
     */
    private function countRemainingWorkingDays(array $dates, \DateTimeImmutable $today): int
    {
        $count = 0;
        foreach ($dates as $date) {
            if ((int) $date->format('N') <= 5 && $date >= $today) {
                ++$count;
            }
        }

        return $count;
    }
}

