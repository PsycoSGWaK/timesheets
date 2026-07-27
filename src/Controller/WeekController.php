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
    ) {
    }

    #[Route('/semaine', name: 'week_current', methods: ['GET'])]
    public function currentWeek(#[CurrentUser] User $user): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->renderWeek($user, (int) $today->format('o'), (int) $today->format('W'));
    }

    #[Route('/semaine/{year}/{week}', name: 'week', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['GET'])]
    public function week(int $year, int $week, #[CurrentUser] User $user): Response
    {
        return $this->renderWeek($user, $year, $week);
    }

    private function renderWeek(User $user, int $year, int $week): Response
    {
        $monday = (new \DateTimeImmutable())->setISODate($year, $week)->setTime(0, 0, 0);

        $dates = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $dates[] = $monday->modify(sprintf('+%d days', $offset));
        }

        $today = new \DateTimeImmutable('today');

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
        ]);
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

