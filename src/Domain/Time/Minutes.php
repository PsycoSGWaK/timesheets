<?php

declare(strict_types=1);

namespace App\Domain\Time;

/**
 * Une quantité de minutes entières.
 *
 * Tout le calcul de l'application se fait en minutes entières et jamais en
 * fractions d'heure : c'est la réponse directe au défaut #10 du proto Excel
 * (arithmétique décimale sur des heures, erreurs d'arrondi cumulées).
 *
 * La valeur peut être négative : une durée qui traverse minuit (fin < début)
 * produit un négatif *représentable et détectable* plutôt qu'un plantage
 * silencieux (défaut #4 du proto).
 */
final readonly class Minutes
{
    private function __construct(
        private int $value,
    ) {
    }

    public static function of(int $minutes): self
    {
        return new self($minutes);
    }

    public static function fromHoursAndMinutes(int $hours, int $minutes): self
    {
        return new self($hours * 60 + $minutes);
    }

    /**
     * Analyse le format horaire d'ADP, strictement `HH:MM` (deux-points, zéros
     * initiaux présents). Le proto utilisait `08h55` : c'était une convention de
     * ressaisie manuelle, pas le format de la source. On refuse tout écart
     * bruyamment (défaut #3), une heure du jour valant 00:00 à 23:59.
     */
    public static function fromClock(string $clock): self
    {
        if (1 !== preg_match('/^(\d{2}):(\d{2})$/', $clock, $matches)) {
            throw new \InvalidArgumentException(
                sprintf('Horaire "%s" invalide : format attendu HH:MM.', $clock),
            );
        }

        $hours = (int) $matches[1];
        $minutes = (int) $matches[2];

        if ($hours > 23 || $minutes > 59) {
            throw new \InvalidArgumentException(
                sprintf('Horaire "%s" invalide : heure du jour hors plage.', $clock),
            );
        }

        return self::fromHoursAndMinutes($hours, $minutes);
    }

    public function value(): int
    {
        return $this->value;
    }

    public function plus(self $other): self
    {
        return new self($this->value + $other->value);
    }

    public function minus(self $other): self
    {
        return new self($this->value - $other->value);
    }

    public function isNegative(): bool
    {
        return $this->value < 0;
    }

    /**
     * Ramène toute valeur négative à zéro. Sert à la pénalité de pause,
     * définie comme max(0, 30 − durée_de_pause).
     */
    public function clampToZero(): self
    {
        return $this->value < 0 ? new self(0) : $this;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function min(self $other): self
    {
        return $this->value <= $other->value ? $this : $other;
    }

    public function max(self $other): self
    {
        return $this->value >= $other->value ? $this : $other;
    }

    /**
     * Rend une heure du jour au format `HH:MM`. Réservé à un instant de la
     * journée (0 à 1439 min) : une valeur négative ou débordante trahit une
     * erreur de calcul et doit remonter, pas s'afficher en douce.
     */
    public function toClock(): string
    {
        if ($this->value < 0 || $this->value > 1439) {
            throw new \LogicException(
                sprintf('%d min ne représente pas une heure du jour affichable.', $this->value),
            );
        }

        return sprintf('%02d:%02d', intdiv($this->value, 60), $this->value % 60);
    }
}
