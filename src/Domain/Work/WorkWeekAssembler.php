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
use App\Entity\Settings;

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
        Settings $settings,
    ): WorkWeek {
        $probativeByDate = $this->groupProbativePunchesByDate($punches);
        $allByDate = $this->groupPunchesByDate($punches);

        $workDays = [];
        $officialFacts = [];
        $estimatedFacts = [];

        foreach ($dates as $date) {
            $key = $date->format('Y-m-d');
            $event = $eventsByDate[$key] ?? null;
            $isRepos = $settings->estJourDeRepos($date);

            // Deux journées distinctes : l'officielle (RTT, heures sup, "Travaillé")
            // ne compte jamais une simple hypothèse, seul le réel ADP l'alimente ;
            // l'estimée sert « Nous » et l'écart, avec le prévisionnel en secours
            // tant que le réel n'est pas arrivé — sans quoi elle resterait à 0h00.
            $officialPunches = $probativeByDate[$key] ?? [];
            $estimatedPunches = $officialPunches ?: ($allByDate[$key] ?? []);

            $officialFact = $this->dailyCalculator->calculate($date, $settings, ...$officialPunches);
            $estimatedFact = $estimatedPunches === $officialPunches
                ? $officialFact
                : $this->dailyCalculator->calculate($date, $settings, ...$estimatedPunches);

            if (!$isRepos) {
                // Tous les pointages du jour (y compris prévisionnels) : un TT en
                // demi-journée précise s'appuie sur des horaires saisis avant tout
                // pointage réel — le seul indice disponible tant qu'aucun n'existe.
                // L'événement est déclaré, donc officiel : les deux versions en
                // héritent de la même façon.
                $officialFact = $this->eventValorizer->valorize($officialFact, $allByDate[$key] ?? [], $event, $settings);
                $estimatedFact = $this->eventValorizer->valorize($estimatedFact, $allByDate[$key] ?? [], $event, $settings);
            }

            $officialFacts[] = $officialFact;
            $estimatedFacts[] = $estimatedFact;

            $reading = $employerReadingsByDate[$key] ?? null;
            $reconciliation = $this->reconciliationDetector->reconcile(
                $date,
                $estimatedFact->workedMinutes(),
                $reading,
                $today,
                $isRepos,
            );

            $workDays[] = new WorkDay($date, $estimatedFact, $reading, $reconciliation, $event);
        }

        return new WorkWeek(
            $this->weeklyCalculator->aggregate($settings, ...$officialFacts),
            $this->weeklyCalculator->aggregate($settings, ...$estimatedFacts),
            $workDays,
        );
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
            // Le réel prime sur le prévisionnel quand les deux existent pour un jour.
            if ($punch->isProbative()) {
                $byDate[$punch->date()->format('Y-m-d')][] = $punch;
            }
        }

        return $byDate;
    }

    /**
     * @param list<PunchEvent> $punches
     *
     * @return array<string, list<PunchEvent>>
     */
    private function groupPunchesByDate(array $punches): array
    {
        $byDate = [];
        foreach ($punches as $punch) {
            $byDate[$punch->date()->format('Y-m-d')][] = $punch;
        }

        return $byDate;
    }
}
