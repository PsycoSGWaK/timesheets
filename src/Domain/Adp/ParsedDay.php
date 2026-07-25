<?php

declare(strict_types=1);

namespace App\Domain\Adp;

use App\Domain\Time\Minutes;

/**
 * Un bloc jour extrait du texte ADP, avant toute interprétation métier.
 *
 * Le parseur ne juge de rien : il restitue ce qu'il lit. Le total d'ADP est
 * conservé tel quel (y compris un 0:00, qui est une observation réelle et non une
 * absence), les pointages sont livrés dans l'ordre, et l'appariement comme la
 * détection d'anomalies appartiennent aux couches suivantes.
 */
final readonly class ParsedDay
{
    /**
     * @param list<Minutes> $punchTimes horodatages des pointages, dans l'ordre
     */
    public function __construct(
        private \DateTimeImmutable $date,
        private ?Minutes $adpTotal,
        private ?string $eventLabel,
        private array $punchTimes,
    ) {
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    /** Total calculé par ADP pour la journée, ou null s'il est absent (repos). */
    public function adpTotal(): ?Minutes
    {
        return $this->adpTotal;
    }

    /** Libellé de l'événement du jour (ex. « Télétravail - En attente »), ou null. */
    public function eventLabel(): ?string
    {
        return $this->eventLabel;
    }

    /**
     * @return list<Minutes>
     */
    public function punchTimes(): array
    {
        return $this->punchTimes;
    }

    public function punchCount(): int
    {
        return \count($this->punchTimes);
    }
}
