<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Work\WorkWeekAssembler;
use App\Repository\EmployerReadingRepository;
use App\Repository\PunchEventRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Affiche une semaine : notre recalcul face au relevé d'ADP, jour par jour, plus
 * l'agrégat hebdomadaire (total, RTT, heures supplémentaires) et les écarts.
 */
final class WeekController extends AbstractController
{
    public function __construct(
        private readonly PunchEventRepository $punches,
        private readonly EmployerReadingRepository $readings,
        private readonly WorkWeekAssembler $assembler,
    ) {
    }

    #[Route('/semaine', name: 'week_current', methods: ['GET'])]
    public function currentWeek(): Response
    {
        $today = new \DateTimeImmutable('today');

        return $this->renderWeek((int) $today->format('o'), (int) $today->format('W'));
    }

    #[Route('/semaine/{year}/{week}', name: 'week', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['GET'])]
    public function week(int $year, int $week): Response
    {
        return $this->renderWeek($year, $week);
    }

    private function renderWeek(int $year, int $week): Response
    {
        $monday = (new \DateTimeImmutable())->setISODate($year, $week)->setTime(0, 0, 0);

        $dates = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $dates[] = $monday->modify(sprintf('+%d days', $offset));
        }

        $workWeek = $this->assembler->assemble(
            $dates,
            $this->punches->findByDates($dates),
            $this->readings->latestMinutesByDates($dates),
            new \DateTimeImmutable('today'),
        );

        $previous = $monday->modify('-7 days');
        $next = $monday->modify('+7 days');

        return $this->render('week/index.html.twig', [
            'workWeek' => $workWeek,
            'week' => $week,
            'monday' => $monday,
            'sunday' => $monday->modify('+6 days'),
            'previous' => ['year' => (int) $previous->format('o'), 'week' => (int) $previous->format('W')],
            'next' => ['year' => (int) $next->format('o'), 'week' => (int) $next->format('W')],
        ]);
    }
}
