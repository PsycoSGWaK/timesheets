<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;
use App\Entity\DayEvent;
use App\Entity\PunchEvent;
use App\Entity\Settings;

/**
 * Valorise une journée par son événement quand aucun pointage probant ne l'a fait
 * — cas limite de la spec §6.5 : une journée de télétravail sans horodatage doit
 * être valorisée par l'événement, pas par une présence qui n'existe pas.
 *
 * Le même traitement s'applique aux congés/jours fériés : le total qu'affiche ADP
 * pour une journée de CP ou de JF est lui aussi crédité, pas nul — ne pas valoriser
 * ces journées produirait de faux écarts « journée perdue » au rapprochement. La
 * référence dépend du code ({@see DayEventCode::referenceDay()}) : le TT est du
 * travail réel (7h24), les absences suivent la base contractuelle (7h00).
 *
 * Un événement ne comble jamais un vrai décompte : il n'agit que sur une journée
 * sans aucun pointage probant. Exception à la simple moitié : un TT en demi-journée
 * s'appuie sur les horaires réels saisis pour ce jour (même prévisionnels — ils sont
 * le seul indice disponible tant qu'aucun pointage réel n'existe), via
 * {@see TeletravailHalfDayCalculator} (règle du 28/07/2026).
 */
final class DayEventValorizer
{
    public function __construct(
        private readonly TeletravailHalfDayCalculator $teletravailHalfDayCalculator = new TeletravailHalfDayCalculator(),
    ) {
    }

    /**
     * @param list<PunchEvent> $dayPunches tous les pointages de ce jour, quelle que
     *                                     soit leur nature (réel ou prévisionnel)
     */
    public function valorize(DayFact $fact, array $dayPunches, ?DayEvent $event, Settings $settings): DayFact
    {
        $probativeCount = 0;
        $byRang = [];
        foreach ($dayPunches as $punch) {
            if ($punch->isProbative()) {
                ++$probativeCount;
            }
            $byRang[$punch->rang()] = $punch;
        }

        if ($probativeCount > 0 || null === $event) {
            return $fact;
        }

        $valorized = $this->valorizedAmount($event, $byRang, $settings);

        return new DayFact(
            $fact->date(),
            $fact->grossPresence(),
            $fact->breakDuration(),
            $fact->breakPenalty(),
            $valorized,
            $fact->anomalies(),
        );
    }

    /**
     * @param array<int, PunchEvent> $dayPunchesByRang
     */
    private function valorizedAmount(DayEvent $event, array $dayPunchesByRang, Settings $settings): Minutes
    {
        if (DayEventCode::Teletravail === $event->code() && DayPortion::Half === $event->portion() && null !== $event->half()) {
            return $this->teletravailHalfDayCalculator->compute($event->half(), $dayPunchesByRang, $settings);
        }

        return $event->portion()->of($event->code()->referenceDay($settings));
    }
}
