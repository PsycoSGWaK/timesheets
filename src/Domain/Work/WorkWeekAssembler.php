<?php

declare(strict_types=1);

namespace App\Domain\Work;

use App\Domain\Day\DailyCalculator;
use App\Domain\Day\DayEventValorizer;
use App\Domain\Reconciliation\ReconciliationDetector;
use App\Domain\Time\Minutes;
use App\Domain\Week\WeeklyCalculator;
use App\Entity\DayEvent;
use App\Entity\PunchEvent;

/**
 * Assemble une semaine affichable à partir de données déjà chargées : pour chacun des
 * sept jours, il recalcule la journée ({@see DailyCalculator}), la valorise par son
 * événement si elle n'a aucun pointage ({@see DayEventValorizer}), la rapproche du
 * relevé ADP ({@see ReconciliationDetector}), puis agrège la semaine
 * ({@see WeeklyCalculator}).
 *
 * Ne touche pas la base : la lecture des pointages, relevés et événements est faite en
 * amont, ce qui garde tout l'assemblage testable en mémoire.
 */
final class WorkWeekAssembler
{
    public function __construct(
        private readonly DailyCalculator $dailyCalculator,
        private readonly WeeklyCalculator $weeklyCalculator,
        private readonly ReconciliationDetector $reconciliationDetector,
        private readonly DayEventValorizer $eventValorizer,
    ) {
    }

    /**
     * @param list<\DateTimeImmutable> $dates                  les sept jours de la semaine
     * @param list<PunchEvent>         $punches                tous les pointages de la semaine
     * @param array<string, Minutes>   $employerReadingsByDate dernier relevé ADP par date « Y-m-d »
     * @param array<string, DayEvent>  $eventsByDate           événement du jour par date « Y-m-d »
     */
    public function assemble(
        array $dates,
        array $punches,
        array $employerReadingsByDate,
        array $eventsByDate,
        \DateTimeImmutable $today,
    ): WorkWeek {
        $probativeByDate = $this->groupProbativePunchesByDate($punches);

        $workDays = [];
        $dayFacts = [];

        foreach ($dates as $date) {
            $key = $date->format('Y-m-d');
            $dayPunches = $probativeByDate[$key] ?? [];
            $event = $eventsByDate[$key] ?? null;

            $fact = $this->dailyCalculator->calculate($date, ...$dayPunches);
            $fact = $this->eventValorizer->valorize($fact, \count($dayPunches), $event);
            $dayFacts[] = $fact;

            $reading = $employerReadingsByDate[$key] ?? null;
            $reconciliation = $this->reconciliationDetector->reconcile(
                $date,
                $fact->workedMinutes(),
                $reading,
                $today,
            );

            $workDays[] = new WorkDay($date, $fact, $reading, $reconciliation, $event);
        }

        return new WorkWeek($this->weeklyCalculator->aggregate(...$dayFacts), $workDays);
    }

    /**
     * @param list<PunchEvent> $punches
     *
     * @return array<string, list<PunchEvent>>
     */
    private function groupProbativePunchesByDate(array $punches): array
    {
        $byDate = [];
        foreach ($punches as $punch) {
            // Seuls les pointages réels comptent dans le décompte officiel ; les
            // prévisionnels servent à la projection, pas à la valeur probante.
            if ($punch->isProbative()) {
                $byDate[$punch->date()->format('Y-m-d')][] = $punch;
            }
        }

        return $byDate;
    }
}
