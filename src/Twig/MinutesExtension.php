<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Formate une durée en minutes entières sous la forme « 7h24 », y compris signée
 * (« -0h44 » pour un écart négatif). Toute l'application raisonne en minutes ; ce
 * filtre est le seul endroit qui les habille pour l'affichage.
 */
final class MinutesExtension extends AbstractExtension
{
    /**
     * @return list<TwigFilter>
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter('duree', $this->formatDuration(...)),
        ];
    }

    public function formatDuration(int $minutes): string
    {
        $absolute = abs($minutes);

        return sprintf(
            '%s%dh%02d',
            $minutes < 0 ? '-' : '',
            intdiv($absolute, 60),
            $absolute % 60,
        );
    }
}
