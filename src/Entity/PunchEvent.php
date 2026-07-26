<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Punch\PunchNature;
use App\Domain\Punch\PunchOrigin;
use App\Domain\Time\Minutes;
use App\Repository\PunchEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un pointage : un horodatage badgé un jour donné.
 *
 * C'est la seule vérité de l'application (spec §4.6). Un pointage est un fait :
 * une fois créé, il n'est jamais modifié. La correction d'une hypothèse passe par
 * la suppression du prévisionnel et l'insertion du réel, jamais par une mutation —
 * d'où l'absence délibérée de tout setter.
 *
 * Deux dimensions indépendantes le qualifient : sa {@see PunchNature} (réel /
 * prévisionnel) et son {@see PunchOrigin} (ADP / saisie manuelle).
 *
 * L'unicité de `(date, time, rang)` garantit un import idempotent : recoller la même
 * semaine ne duplique aucun pointage (source-adp.md §5.3).
 */
#[ORM\Entity(repositoryClass: PunchEventRepository::class)]
#[ORM\Table(name: 'punch_event')]
#[ORM\UniqueConstraint(name: 'uniq_punch_slot', columns: ['date', 'time', 'rang'])]
final class PunchEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Minutes depuis minuit, de 0 (00:00) à 1439 (23:59). */
    #[ORM\Column(type: 'integer')]
    private int $time;

    /** Rang du pointage dans la journée (1er, 2e…), base 1. */
    #[ORM\Column(type: 'smallint')]
    private int $rang;

    #[ORM\Column(enumType: PunchNature::class)]
    private PunchNature $nature;

    #[ORM\Column(enumType: PunchOrigin::class)]
    private PunchOrigin $origin;

    private function __construct(
        \DateTimeImmutable $date,
        Minutes $time,
        int $rang,
        PunchNature $nature,
        PunchOrigin $origin,
    ) {
        $minutes = $time->value();
        if ($minutes < 0 || $minutes > 1439) {
            throw new \InvalidArgumentException(
                sprintf('Un pointage doit être une heure du jour (0–1439 min), reçu %d.', $minutes),
            );
        }

        if ($rang < 1) {
            throw new \InvalidArgumentException(
                sprintf('Le rang d\'un pointage commence à 1, reçu %d.', $rang),
            );
        }

        // La date porte une journée, pas un instant : on écrase toute heure résiduelle.
        $this->date = $date->setTime(0, 0, 0, 0);
        $this->time = $minutes;
        $this->rang = $rang;
        $this->nature = $nature;
        $this->origin = $origin;
    }

    /** Pointage collé depuis ADP : un fait constaté. */
    public static function realFromAdp(\DateTimeImmutable $date, Minutes $time, int $rang): self
    {
        return new self($date, $time, $rang, PunchNature::Reel, PunchOrigin::Adp);
    }

    /** Pointage saisi à la main par anticipation : une hypothèse de projection. */
    public static function provisional(\DateTimeImmutable $date, Minutes $time, int $rang): self
    {
        return new self($date, $time, $rang, PunchNature::Previsionnel, PunchOrigin::SaisieManuelle);
    }

    /** Correction manuelle : un badge réel qu'ADP a manqué, comblé à la main. */
    public static function manualCorrection(\DateTimeImmutable $date, Minutes $time, int $rang): self
    {
        return new self($date, $time, $rang, PunchNature::Reel, PunchOrigin::SaisieManuelle);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function time(): Minutes
    {
        return Minutes::of($this->time);
    }

    public function rang(): int
    {
        return $this->rang;
    }

    public function nature(): PunchNature
    {
        return $this->nature;
    }

    public function origin(): PunchOrigin
    {
        return $this->origin;
    }

    public function isProbative(): bool
    {
        return $this->nature->isProbative();
    }

    /**
     * Deux pointages occupent le même créneau s'ils tombent le même jour au même rang.
     * Le remplacement d'un prévisionnel par un réel se joue sur ce créneau, quelle que
     * soit l'heure exacte.
     */
    public function isSameSlotAs(self $other): bool
    {
        return $this->rang === $other->rang
            && $this->date->format('Y-m-d') === $other->date->format('Y-m-d');
    }
}
