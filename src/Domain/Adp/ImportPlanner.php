<?php

declare(strict_types=1);

namespace App\Domain\Adp;

use App\Domain\Time\Minutes;
use App\Entity\EmployerReading;
use App\Entity\PunchEvent;
use App\Entity\User;

/**
 * Décide, sans rien persister, ce qu'un import ADP doit produire.
 *
 * Deux régimes distincts :
 *  - les pointages sont des faits, donc dédupliqués — un pointage réel déjà présent
 *    au même créneau (date, heure, rang) n'est pas recréé, et l'arrivée du réel efface
 *    les pointages prévisionnels de la journée (l'hypothèse cède devant la donnée) ;
 *  - le total d'ADP est une observation horodatée, donc ajouté à chaque import : c'est
 *    l'append-only qui garde l'historique des révisions du décompte employeur (§4bis).
 */
final class ImportPlanner
{
    /**
     * @param array<string, list<PunchEvent>> $existingPunchesByDate pointages déjà en base
     *        pour cet utilisateur, indexés par date « Y-m-d »
     */
    public function plan(
        User $user,
        ParsedWeek $week,
        \DateTimeImmutable $observedAt,
        array $existingPunchesByDate = [],
    ): ImportPlan {
        $readings = [];
        $toCreate = [];
        $toSupersede = [];

        foreach ($week->days() as $day) {
            $adpTotal = $day->adpTotal();
            if (null !== $adpTotal) {
                $readings[] = EmployerReading::record($user, $day->date(), $adpTotal, $observedAt);
            }

            if ([] === $day->punchTimes()) {
                continue;
            }

            $existing = $existingPunchesByDate[$day->date()->format('Y-m-d')] ?? [];

            // L'arrivée de données réelles pour la journée périme ses pointages prévisionnels.
            foreach ($existing as $punch) {
                if (!$punch->isProbative()) {
                    $toSupersede[] = $punch;
                }
            }

            $rang = 1;
            foreach ($day->punchTimes() as $time) {
                if (!$this->alreadyRecorded($existing, $time, $rang)) {
                    $toCreate[] = PunchEvent::realFromAdp($user, $day->date(), $time, $rang);
                }
                ++$rang;
            }
        }

        return new ImportPlan($readings, $toCreate, $toSupersede);
    }

    /**
     * @param list<PunchEvent> $existing
     */
    private function alreadyRecorded(array $existing, Minutes $time, int $rang): bool
    {
        foreach ($existing as $punch) {
            if ($punch->rang() === $rang
                && $punch->isProbative()
                && $punch->time()->value() === $time->value()) {
                return true;
            }
        }

        return false;
    }
}
