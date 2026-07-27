<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayHalf;
use App\Domain\Day\DayPortion;
use App\Repository\DayEventRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Un événement déclaré par l'utilisateur sur une journée : congé, jour férié,
 * télétravail... (spec §2, les codes exprimés en jours).
 *
 * Au plus un événement par jour et par utilisateur — pas de setter : on supprime et
 * on redéclare, comme pour {@see PunchEvent}.
 */
#[ORM\Entity(repositoryClass: DayEventRepository::class)]
#[ORM\Table(name: 'day_event')]
#[ORM\UniqueConstraint(name: 'uniq_day_event_slot', columns: ['user_id', 'date'])]
final class DayEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(type: 'date_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(enumType: DayEventCode::class)]
    private DayEventCode $code;

    // Colonne nommee "quotite" et non "portion" : PORTION est un mot reserve MariaDB
    // (tables temporelles, FOR PORTION OF), meme piege que "rang" face a RANK.
    #[ORM\Column(name: 'quotite', enumType: DayPortion::class)]
    private DayPortion $portion;

    // Utile seulement pour un TT en demi-journée (matin/après-midi n'ont pas le
    // même calcul, {@see \App\Domain\Day\TeletravailHalfDayCalculator}) ; nul pour
    // tous les autres cas (jour plein, ou demi-journée d'une absence CP/CA/RTT/JF).
    #[ORM\Column(nullable: true, enumType: DayHalf::class)]
    private ?DayHalf $half;

    private function __construct(User $user, \DateTimeImmutable $date, DayEventCode $code, DayPortion $portion, ?DayHalf $half)
    {
        $this->user = $user;
        $this->date = $date->setTime(0, 0, 0, 0);
        $this->code = $code;
        $this->portion = $portion;
        $this->half = $half;
    }

    public static function declare(
        User $user,
        \DateTimeImmutable $date,
        DayEventCode $code,
        DayPortion $portion = DayPortion::Full,
        ?DayHalf $half = null,
    ): self {
        return new self($user, $date, $code, $portion, $half);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function user(): User
    {
        return $this->user;
    }

    public function date(): \DateTimeImmutable
    {
        return $this->date;
    }

    public function code(): DayEventCode
    {
        return $this->code;
    }

    public function portion(): DayPortion
    {
        return $this->portion;
    }

    public function half(): ?DayHalf
    {
        return $this->half;
    }
}
