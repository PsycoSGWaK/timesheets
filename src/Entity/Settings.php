<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Time\Minutes;
use App\Repository\SettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le paramétrage d'un utilisateur : les seuils que les calculateurs du domaine
 * appliquaient jusqu'ici en dur (pause minimale, fenêtre autorisée, journées de
 * référence, plafond RTT — spec §3).
 *
 * Les deux seuils hebdomadaires (35h, 37h) ne sont pas stockés : ils se déduisent
 * des journées de référence (35h = 7h00 × 5, 37h = 7h24 × 5 — confirmé par
 * l'horaire théorique ADP, source-adp §3.2), pour qu'ils ne puissent jamais diverger
 * d'une modification de l'un sans l'autre.
 *
 * Un seul jeu de valeurs par utilisateur, modifié en place — contrairement aux
 * pointages, il n'y a pas de raison métier de conserver un historique des anciens
 * réglages.
 */
#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Table(name: 'settings')]
#[ORM\UniqueConstraint(name: 'uniq_settings_user', columns: ['user_id'])]
final class Settings
{
    private const JOURS_OUVRES_PAR_SEMAINE = 5;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'integer')]
    private int $pauseMinimale;

    #[ORM\Column(type: 'integer')]
    private int $fenetreDebut;

    #[ORM\Column(type: 'integer')]
    private int $fenetreFin;

    #[ORM\Column(type: 'integer')]
    private int $journeeReferenceContractuelle;

    #[ORM\Column(type: 'integer')]
    private int $journeeReferenceEffective;

    #[ORM\Column(type: 'integer')]
    private int $rttMax;

    private function __construct(
        User $user,
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
    ) {
        $this->user = $user;
        $this->applyAndValidate(
            $pauseMinimale,
            $fenetreDebut,
            $fenetreFin,
            $journeeReferenceContractuelle,
            $journeeReferenceEffective,
            $rttMax,
        );
    }

    /**
     * Les valeurs jusqu'ici en dur dans les calculateurs (spec §3, confirmées par
     * Guillaume le 27/07/2026) : pause 30 min, fenêtre 11h30-14h00, journée
     * contractuelle 7h00, journée effective 7h24, RTT plafonné à 2h/semaine.
     */
    public static function defaults(User $user): self
    {
        return new self(
            $user,
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
        );
    }

    public function update(
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
    ): void {
        $this->applyAndValidate(
            $pauseMinimale,
            $fenetreDebut,
            $fenetreFin,
            $journeeReferenceContractuelle,
            $journeeReferenceEffective,
            $rttMax,
        );
    }

    private function applyAndValidate(
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
    ): void {
        foreach ([
            'pauseMinimale' => $pauseMinimale,
            'fenetreDebut' => $fenetreDebut,
            'fenetreFin' => $fenetreFin,
            'journeeReferenceContractuelle' => $journeeReferenceContractuelle,
            'journeeReferenceEffective' => $journeeReferenceEffective,
            'rttMax' => $rttMax,
        ] as $name => $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException(sprintf('%s ne peut être négatif, reçu %d.', $name, $value));
            }
        }

        if ($fenetreFin <= $fenetreDebut) {
            throw new \InvalidArgumentException('La fenêtre de pause doit se terminer après avoir commencé.');
        }

        $this->pauseMinimale = $pauseMinimale;
        $this->fenetreDebut = $fenetreDebut;
        $this->fenetreFin = $fenetreFin;
        $this->journeeReferenceContractuelle = $journeeReferenceContractuelle;
        $this->journeeReferenceEffective = $journeeReferenceEffective;
        $this->rttMax = $rttMax;
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function pauseMinimale(): Minutes
    {
        return Minutes::of($this->pauseMinimale);
    }

    public function fenetreDebut(): Minutes
    {
        return Minutes::of($this->fenetreDebut);
    }

    public function fenetreFin(): Minutes
    {
        return Minutes::of($this->fenetreFin);
    }

    public function journeeReferenceContractuelle(): Minutes
    {
        return Minutes::of($this->journeeReferenceContractuelle);
    }

    public function journeeReferenceEffective(): Minutes
    {
        return Minutes::of($this->journeeReferenceEffective);
    }

    public function rttMax(): Minutes
    {
        return Minutes::of($this->rttMax);
    }

    /** 35h = journée contractuelle × 5 jours ouvrés. */
    public function weeklyReference(): Minutes
    {
        return Minutes::of($this->journeeReferenceContractuelle * self::JOURS_OUVRES_PAR_SEMAINE);
    }

    /** 37h = journée effective × 5 jours ouvrés. */
    public function weeklyBascule(): Minutes
    {
        return Minutes::of($this->journeeReferenceEffective * self::JOURS_OUVRES_PAR_SEMAINE);
    }
}
