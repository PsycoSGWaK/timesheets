<?php

declare(strict_types=1);

namespace App\Week;

use App\Domain\Projection\LeaveEstimate;

/**
 * Les données d'édition d'une journée (spec §4.6), portées par le panneau affiché
 * dans « Ma semaine » plutôt que sur un écran séparé.
 */
final readonly class DayEditPanel
{
    /**
     * @param list<array{field: string, value: string, readonly: bool}> $slots
     */
    public function __construct(
        private \DateTimeImmutable $date,
        private array $slots,
        private ?LeaveEstimate $estimate,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    /**
     * @return list<array{field: string, value: string, readonly: bool}>
     */
    public function slots(): array
    {
        return $this->slots;
    }

    public function estimate(): ?LeaveEstimate
    {
        return $this->estimate;
    }
}
