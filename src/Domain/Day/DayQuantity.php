<?php

declare(strict_types=1);

namespace App\Domain\Day;

/**
 * Une quantité de journées entières ou demi-journées, exprimée en demi-jours
 * entiers pour éviter tout calcul en virgule flottante — même logique que
 * {@see \App\Domain\Time\Minutes} pour les minutes.
 *
 * Sert au décompte annuel des événements posés en jours (CP/TT/RTT/JF, spec du
 * 29/07/2026) : combien de jours ont été déclarés face au quota paramétré.
 */
final readonly class DayQuantity
{
    private function __construct(
        private int $halfDays,
    ) {
    }

    public static function ofHalfDays(int $halfDays): self
    {
        return new self($halfDays);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Analyse une saisie utilisateur en jours, format français : "25" ou "25,5"
     * (jamais de point, jamais plus d'une décimale — seules les demi-journées
     * existent, spec §2).
     */
    public static function fromDayString(string $value): self
    {
        if (1 !== preg_match('/^(\d+)(,5)?$/', trim($value), $matches)) {
            throw new \InvalidArgumentException(
                sprintf('Quantité de jours "%s" invalide : format attendu "25" ou "25,5".', $value),
            );
        }

        $halfDays = ((int) $matches[1]) * 2 + (isset($matches[2]) ? 1 : 0);

        return new self($halfDays);
    }

    public function halfDays(): int
    {
        return $this->halfDays;
    }

    public function plus(self $other): self
    {
        return new self($this->halfDays + $other->halfDays);
    }

    public function minus(self $other): self
    {
        return new self($this->halfDays - $other->halfDays);
    }

    public function isNegative(): bool
    {
        return $this->halfDays < 0;
    }

    /** Ramène toute valeur négative à zéro (le quota dépassé n'a pas de "reste"). */
    public function clampToZero(): self
    {
        return $this->halfDays < 0 ? new self(0) : $this;
    }

    /** « 25 j » ou « 24,5 j » — jamais de virgule flottante pour y arriver. */
    public function toLabel(): string
    {
        return sprintf('%s j', $this->toDayString());
    }

    /** Même écriture que {@see self::toLabel()} mais sans le suffixe, pour préremplir un champ de saisie. */
    public function toDayString(): string
    {
        $days = intdiv($this->halfDays, 2);
        $remainder = $this->halfDays % 2;

        return 0 === $remainder ? (string) $days : sprintf('%d,5', $days);
    }
}
