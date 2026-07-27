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

    #[ORM\Column(type: 'integer')]
    private int $finApresMidiTeletravail;

    /**
     * Jours de la semaine sans rien attendu (spec du 28/07/2026) : numéros ISO-8601
     * (1 = lundi … 7 = dimanche), comme {@see \DateTimeImmutable::format()} avec `N`.
     *
     * @var list<int>
     */
    #[ORM\Column]
    private array $joursDeRepos;

    /**
     * @param list<int> $joursDeRepos
     */
    private function __construct(
        User $user,
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
        int $finApresMidiTeletravail,
        array $joursDeRepos,
    ) {
        $this->user = $user;
        $this->applyAndValidate(
            $pauseMinimale,
            $fenetreDebut,
            $fenetreFin,
            $journeeReferenceContractuelle,
            $journeeReferenceEffective,
            $rttMax,
            $finApresMidiTeletravail,
            $joursDeRepos,
        );
    }

    /**
     * Les valeurs jusqu'ici en dur dans les calculateurs (spec §3, confirmées par
     * Guillaume le 27/07/2026) : pause 30 min, fenêtre 11h30-14h00, journée
     * contractuelle 7h00, journée effective 7h24, RTT plafonné à 2h/semaine.
     * Fin de demi-journée TT après-midi à 16h00, confirmée le 28/07/2026. Jours de
     * repos par défaut : samedi et dimanche, pour matcher la projection Lun-Ven déjà
     * en place avant que ce réglage n'existe.
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
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
        );
    }

    /**
     * @param list<int> $joursDeRepos
     */
    public function update(
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
        int $finApresMidiTeletravail,
        array $joursDeRepos,
    ): void {
        $this->applyAndValidate(
            $pauseMinimale,
            $fenetreDebut,
            $fenetreFin,
            $journeeReferenceContractuelle,
            $journeeReferenceEffective,
            $rttMax,
            $finApresMidiTeletravail,
            $joursDeRepos,
        );
    }

    /**
     * @param list<int> $joursDeRepos
     */
    private function applyAndValidate(
        int $pauseMinimale,
        int $fenetreDebut,
        int $fenetreFin,
        int $journeeReferenceContractuelle,
        int $journeeReferenceEffective,
        int $rttMax,
        int $finApresMidiTeletravail,
        array $joursDeRepos,
    ): void {
        foreach ([
            'pauseMinimale' => $pauseMinimale,
            'fenetreDebut' => $fenetreDebut,
            'fenetreFin' => $fenetreFin,
            'journeeReferenceContractuelle' => $journeeReferenceContractuelle,
            'journeeReferenceEffective' => $journeeReferenceEffective,
            'rttMax' => $rttMax,
            'finApresMidiTeletravail' => $finApresMidiTeletravail,
        ] as $name => $value) {
            if ($value < 0) {
                throw new \InvalidArgumentException(sprintf('%s ne peut être négatif, reçu %d.', $name, $value));
            }
        }

        if ($fenetreFin <= $fenetreDebut) {
            throw new \InvalidArgumentException('La fenêtre de pause doit se terminer après avoir commencé.');
        }

        if ($finApresMidiTeletravail <= $fenetreDebut) {
            throw new \InvalidArgumentException(
                'La fin de demi-journée TT après-midi doit rester après le début de la fenêtre de pause.',
            );
        }

        $joursDeRepos = array_values(array_unique($joursDeRepos));
        foreach ($joursDeRepos as $jour) {
            if ($jour < 1 || $jour > 7) {
                throw new \InvalidArgumentException(
                    sprintf('Un jour de repos doit être un numéro ISO 1 (lundi) à 7 (dimanche), reçu %d.', $jour),
                );
            }
        }
        if (7 === \count($joursDeRepos)) {
            throw new \InvalidArgumentException('Au moins un jour de la semaine doit rester un jour ouvré.');
        }

        $this->pauseMinimale = $pauseMinimale;
        $this->fenetreDebut = $fenetreDebut;
        $this->fenetreFin = $fenetreFin;
        $this->journeeReferenceContractuelle = $journeeReferenceContractuelle;
        $this->journeeReferenceEffective = $journeeReferenceEffective;
        $this->rttMax = $rttMax;
        $this->finApresMidiTeletravail = $finApresMidiTeletravail;
        sort($joursDeRepos);
        $this->joursDeRepos = $joursDeRepos;
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

    public function finApresMidiTeletravail(): Minutes
    {
        return Minutes::of($this->finApresMidiTeletravail);
    }

    /**
     * @return list<int> numéros ISO 1 (lundi) à 7 (dimanche), triés
     */
    public function joursDeRepos(): array
    {
        return $this->joursDeRepos;
    }

    public function estJourDeRepos(\DateTimeImmutable $date): bool
    {
        return \in_array((int) $date->format('N'), $this->joursDeRepos, true);
    }

    /** Les 7 jours de la semaine, moins les jours de repos déclarés. */
    public function joursOuvresParSemaine(): int
    {
        return 7 - \count($this->joursDeRepos);
    }

    /** 35h par défaut = journée contractuelle × jours ouvrés (7 − jours de repos). */
    public function weeklyReference(): Minutes
    {
        return Minutes::of($this->journeeReferenceContractuelle * $this->joursOuvresParSemaine());
    }

    /** 37h par défaut = journée effective × jours ouvrés (7 − jours de repos). */
    public function weeklyBascule(): Minutes
    {
        return Minutes::of($this->journeeReferenceEffective * $this->joursOuvresParSemaine());
    }
}
