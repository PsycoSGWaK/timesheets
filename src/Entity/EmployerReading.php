<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Time\Minutes;
use App\Repository\EmployerReadingRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un relevé horodaté du total qu'ADP affiche pour une journée.
 *
 * Le total de l'employeur est une observation, jamais une donnée maître : il peut
 * changer après coup (consolidation nocturne, validation d'un événement,
 * régularisation). On ne l'écrase donc jamais — au réimport, une nouvelle observation
 * est ajoutée (source-adp §4bis). Plusieurs relevés coexistent pour une même date ;
 * le plus récent (`observedAt`) fait foi, et l'historique garde une trace du type
 * « le 24/07, ADP annonçait 0:00 pour le 23/07 ».
 *
 * C'est une entité de contrôle : nos propres `DayFact` restent la référence, ce relevé
 * sert à mesurer l'écart.
 */
#[ORM\Entity(repositoryClass: EmployerReadingRepository::class)]
#[ORM\Table(name: 'employer_reading')]
#[ORM\Index(name: 'idx_reading_date', columns: ['date'])]
final class EmployerReading
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    /** Total ADP de la journée, en minutes (0 est une valeur, pas une absence). */
    #[ORM\Column(type: 'integer')]
    private int $employerMinutes;

    /** Instant où cette observation a été relevée (import). */
    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $observedAt;

    private function __construct(
        \DateTimeImmutable $date,
        Minutes $employerTotal,
        \DateTimeImmutable $observedAt,
    ) {
        if ($employerTotal->value() < 0) {
            throw new \InvalidArgumentException(
                sprintf('Un total employeur ne peut être négatif, reçu %d.', $employerTotal->value()),
            );
        }

        $this->date = $date->setTime(0, 0, 0, 0);
        $this->employerMinutes = $employerTotal->value();
        $this->observedAt = $observedAt;
    }

    public static function record(
        \DateTimeImmutable $date,
        Minutes $employerTotal,
        \DateTimeImmutable $observedAt,
    ): self {
        return new self($date, $employerTotal, $observedAt);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function employerMinutes(): Minutes
    {
        return Minutes::of($this->employerMinutes);
    }

    public function observedAt(): \DateTimeImmutable
    {
        return $this->observedAt;
    }
}
