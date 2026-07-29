<?php

declare(strict_types=1);

namespace App\Week;

use App\Domain\Work\WorkWeek;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\DayEventRepository;
use App\Repository\EmployerReadingRepository;
use App\Repository\PunchEventRepository;

/**
 * Charge une semaine affichable pour un utilisateur : va chercher les trois
 * collections dont {@see WorkWeekAssembler} a besoin (pointages, relevés ADP,
 * événements), puis les lui passe. Le seul point commun à
 * {@see \App\Controller\WeekController} et {@see \App\Controller\BalanceController},
 * qui en avaient chacun leur propre copie avant extraction.
 */
final class WeekLoader
{
    public function __construct(
        private readonly PunchEventRepository $punches,
        private readonly EmployerReadingRepository $readings,
        private readonly DayEventRepository $events,
        private readonly WorkWeekAssembler $assembler,
    ) {
    }

    /**
     * @param list<\DateTimeImmutable> $dates
     */
    public function load(User $user, array $dates, \DateTimeImmutable $today, Settings $settings): WorkWeek
    {
        return $this->assembler->assemble(
            $dates,
            $this->punches->findByDates($user, $dates),
            $this->readings->latestMinutesByDates($user, $dates),
            $this->events->findByDates($user, $dates),
            $today,
            $settings,
        );
    }
}
