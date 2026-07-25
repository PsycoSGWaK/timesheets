<?php

declare(strict_types=1);

namespace App\Domain\Punch;

/**
 * La nature d'un pointage : est-ce un fait constaté ou une hypothèse de travail ?
 *
 * - `Reel`         : collé depuis ADP, immuable. C'est ce qui s'est physiquement passé.
 * - `Previsionnel` : saisi à la main par anticipation. Une hypothèse qui alimente la
 *                    projection, exclue des soldes et des exports, et remplacée par le
 *                    réel dès qu'il arrive.
 *
 * Les deux natures ne se mélangent jamais dans les totaux officiels (spec §4.6).
 *
 * À ne pas confondre avec l'origine d'un pointage (ADP / saisie manuelle), qui est
 * une dimension distincte portée par l'entité PunchEvent.
 */
enum PunchNature: string
{
    case Reel = 'reel';
    case Previsionnel = 'previsionnel';

    /**
     * Seul le réel a valeur probante : il compte dans les soldes et les exports.
     */
    public function isProbative(): bool
    {
        return self::Reel === $this;
    }

    /**
     * Un pointage réel remplace le prévisionnel du même créneau. L'inverse ne doit
     * jamais arriver : une hypothèse n'efface pas un fait.
     */
    public function supersedes(self $other): bool
    {
        return self::Reel === $this && self::Previsionnel === $other;
    }

    public function label(): string
    {
        return match ($this) {
            self::Reel => 'Réel',
            self::Previsionnel => 'Prévisionnel',
        };
    }
}
