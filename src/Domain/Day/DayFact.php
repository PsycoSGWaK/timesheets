<?php

declare(strict_types=1);

namespace App\Domain\Day;

use App\Domain\Time\Minutes;

/**
 * Le résultat du recalcul d'une journée : le décompte indépendant de l'application,
 * celui que l'on confronte au total d'ADP.
 *
 * Objet dérivé et immuable, produit par {@see DailyCalculator} à partir des pointages.
 * Il n'est pas persisté : il se recalcule à la demande, la source de vérité restant
 * les pointages eux-mêmes.
 */
final readonly class DayFact
{
    /**
     * @param list<DailyAnomaly> $anomalies
     */
    public function __construct(
        private \DateTimeImmutable $date,
        private Minutes $grossPresence,
        private ?Minutes $breakDuration,
        private Minutes $breakPenalty,
        private Minutes $workedMinutes,
        private array $anomalies,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Présence brute : la somme des intervalles travaillés, avant pénalité de pause. */
    public function grossPresence(): Minutes
    {
        return $this->grossPresence;
    }

    /** Durée de la pause déjeuner, ou null si la journée n'en comporte pas. */
    public function breakDuration(): ?Minutes
    {
        return $this->breakDuration;
    }

    /** Minutes retranchées au titre d'une pause trop courte (max 30). */
    public function breakPenalty(): Minutes
    {
        return $this->breakPenalty;
    }

    /** Temps de travail retenu : présence brute moins pénalité de pause. */
    public function workedMinutes(): Minutes
    {
        return $this->workedMinutes;
    }

    /**
     * @return list<DailyAnomaly>
     */
    public function anomalies(): array
    {
        return $this->anomalies;
    }

    public function hasAnomaly(DailyAnomaly $anomaly): bool
    {
        return \in_array($anomaly, $this->anomalies, true);
    }

    public function isClean(): bool
    {
        return [] === $this->anomalies;
    }
}
