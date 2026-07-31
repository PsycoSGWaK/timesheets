<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Time\Minutes;
use App\Repository\BalanceMovementRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un mouvement sur un compteur : le RTT (crédité chaque semaine, débité quand on
 * pose un jour) ou l'un des deux destins d'une heure supplémentaire — Récupération,
 * Paiement (spec §2). Append-only, comme {@see EmployerReading} : jamais
 * modifié ni supprimé pour corriger une erreur, on ajoute un mouvement compensateur.
 *
 * Le solde d'un compteur n'est jamais stocké : il se recalcule en sommant ses
 * mouvements ({@see \App\Repository\BalanceMovementRepository::balanceFor()}).
 */
#[ORM\Entity(repositoryClass: BalanceMovementRepository::class)]
#[ORM\Table(name: 'balance_movement')]
#[ORM\Index(name: 'idx_balance_user_counter', columns: ['user_id', 'counter'])]
final class BalanceMovement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(enumType: BalanceCounter::class)]
    private BalanceCounter $counter;

    /** Signé : positif pour un crédit, négatif pour un débit. */
    #[ORM\Column(type: 'integer')]
    private int $amount;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $motif;

    /** Le jour ou la semaine (lundi) concerné par le mouvement. */
    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Instant où le mouvement a été enregistré. */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $recordedAt;

    private function __construct(
        User $user,
        BalanceCounter $counter,
        int $amount,
        \DateTimeImmutable $date,
        \DateTimeImmutable $recordedAt,
        ?string $motif,
    ) {
        $this->user = $user;
        $this->counter = $counter;
        $this->amount = $amount;
        $this->date = $date->setTime(0, 0, 0, 0);
        $this->recordedAt = $recordedAt;
        $this->motif = $motif;
    }

    public static function credit(
        User $user,
        BalanceCounter $counter,
        Minutes $amount,
        \DateTimeImmutable $date,
        \DateTimeImmutable $recordedAt,
        ?string $motif = null,
    ): self {
        return new self($user, $counter, self::positiveMagnitude($amount), $date, $recordedAt, $motif);
    }

    public static function debit(
        User $user,
        BalanceCounter $counter,
        Minutes $amount,
        \DateTimeImmutable $date,
        \DateTimeImmutable $recordedAt,
        ?string $motif = null,
    ): self {
        return new self($user, $counter, -self::positiveMagnitude($amount), $date, $recordedAt, $motif);
    }

    private static function positiveMagnitude(Minutes $amount): int
    {
        if ($amount->value() < 0) {
            throw new \InvalidArgumentException(
                sprintf('Un montant de mouvement se fournit positif ; le sens (crédit/débit) porte le signe, reçu %d.', $amount->value()),
            );
        }

        return $amount->value();
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function counter(): BalanceCounter
    {
        return $this->counter;
    }

    public function amount(): Minutes
    {
        return Minutes::of($this->amount);
    }

    public function isCredit(): bool
    {
        return $this->amount > 0;
    }

    public function motif(): ?string
    {
        return $this->motif;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function recordedAt(): \DateTimeImmutable
    {
        return $this->recordedAt;
    }
}
