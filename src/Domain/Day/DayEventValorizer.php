<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;
use App\Entity\DayEvent;

/**
 * Valorise une journée par son événement quand aucun pointage ne l'a fait — cas
 * limite de la spec §6.5 : une journée de télétravail sans horodatage doit être
 * valorisée par l'événement, pas par une présence qui n'existe pas.
 *
 * Le même traitement s'applique aux congés/jours fériés : le total qu'affiche ADP
 * pour une journée de CP ou de JF est lui aussi crédité, pas nul — ne pas valoriser
 * ces journées produirait de faux écarts « journée perdue » au rapprochement.
 *
 * Un événement ne comble jamais un vrai décompte : il n'agit que sur une journée
 * sans aucun pointage.
 */
final class DayEventValorizer
{
    public function valorize(DayFact $fact, int $punchCount, ?DayEvent $event): DayFact
    {
        if ($punchCount > 0 || null === $event) {
            return $fact;
        }

        $valorized = $event->portion()->of(Minutes::fromHoursAndMinutes(7, 24));

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
