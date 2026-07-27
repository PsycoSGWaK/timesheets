<?php

declare(strict_types=1);

namespace App\Domain\Week;

/**
 * Résout les sept dates (lundi à dimanche) d'une semaine ISO. Extrait de
 * `WeekController` car réutilisé par les actions de crédit RTT et d'allocation
 * des heures supplémentaires, qui doivent recalculer la même semaine.
 */
final class IsoWeek
{
    /**
     * @return list<\DateTimeImmutable>
     */
    public static function dates(int $year, int $week): array
    {
        $monday = (new \DateTimeImmutable())->setISODate($year, $week)->setTime(0, 0, 0);

        $dates = [];
        for ($offset = 0; $offset < 7; ++$offset) {
            $dates[] = $monday->modify(sprintf('+%d days', $offset));
        }

        return $dates;
    }
}
