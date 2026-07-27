<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Entity\DayEvent;
use App\Entity\Settings;

/**
 * Valorise une journée par son événement quand aucun pointage ne l'a fait — cas
 * limite de la spec §6.5 : une journée de télétravail sans horodatage doit être
 * valorisée par l'événement, pas par une présence qui n'existe pas.
 *
 * Le même traitement s'applique aux congés/jours fériés : le total qu'affiche ADP
 * pour une journée de CP ou de JF est lui aussi crédité, pas nul — ne pas valoriser
 * ces journées produirait de faux écarts « journée perdue » au rapprochement. La
 * référence dépend du code ({@see DayEventCode::referenceDay()}) : le TT est du
 * travail réel (7h24), les absences suivent la base contractuelle (7h00).
 *
 * Un événement ne comble jamais un vrai décompte : il n'agit que sur une journée
 * sans aucun pointage.
 */
final class DayEventValorizer
{
    public function valorize(DayFact $fact, int $punchCount, ?DayEvent $event, Settings $settings): DayFact
    {
        if ($punchCount > 0 || null === $event) {
            return $fact;
        }

        $valorized = $event->portion()->of($event->code()->referenceDay($settings));

        return new DayFact(
            $fact->date(),
            $fact->grossPresence(),
            $fact->breakDuration(),
            $fact->breakPenalty(),
            $valorized,
            $fact->anomalies(),
        );
    }
}
