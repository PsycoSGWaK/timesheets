<?php

declare(strict_types=1);

namespace App\Domain\Day;

/**
 * Le décompte annuel d'un code d'événement (spec du 29/07/2026) : combien de jours
 * ont été posés face au quota paramétré ({@see \App\Entity\Settings::quotaAnnuel()}).
 */
final readonly class EventQuotaOverview
{
    public function __construct(
        private DayEventCode $code,
        private DayQuantity $used,
        private DayQuantity $quota,
    ) {
    }

    public function code(): DayEventCode
    {
        return $this->code;
    }

    public function used(): DayQuantity
    {
        return $this->used;
    }

    public function quota(): DayQuantity
    {
        return $this->quota;
    }

    /** Jamais négatif : dépasser le quota n'affiche pas un "reste" en dessous de zéro. */
    public function remaining(): DayQuantity
    {
        return $this->quota->minus($this->used)->clampToZero();
    }
}
