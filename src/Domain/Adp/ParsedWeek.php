<?php

declare(strict_types=1);

namespace App\Domain\Adp;

/**
 * Le résultat de l'analyse d'un texte ADP collé : les blocs jour d'une semaine,
 * rattachés à l'année fournie (le texte source ne la contient pas, source-adp §2).
 */
final readonly class ParsedWeek
{
    /**
     * @param list<ParsedDay> $days
     */
    public function __construct(
        private int $year,
        private array $days,
    ) {
    }

    public function year(): int
    {
        return $this->year;
    }

    /**
     * @return list<ParsedDay>
     */
    public function days(): array
    {
        return $this->days;
    }

    public function dayCount(): int
    {
        return \count($this->days);
    }
}
