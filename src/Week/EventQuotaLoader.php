<?php

declare(strict_types=1);

namespace App\Week;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayQuantity;
use App\Domain\Day\EventQuotaOverview;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\DayEventRepository;

/**
 * Charge le décompte annuel des événements posés (CP/TT/RTT/JF, spec du
 * 29/07/2026) face au quota paramétré — affiché dans Compteurs.
 */
final class EventQuotaLoader
{
    public function __construct(
        private readonly DayEventRepository $events,
    ) {
    }

    /**
     * @return list<EventQuotaOverview>
     */
    public function load(User $user, Settings $settings, int $year): array
    {
        $overviews = [];
        foreach (DayEventCode::withAnnualQuota() as $code) {
            $used = DayQuantity::zero();
            foreach ($this->events->findByCodeAndYear($user, $code, $year) as $event) {
                $used = $used->plus($event->portion()->toDayQuantity());
            }

            $overviews[] = new EventQuotaOverview($code, $used, $settings->quotaAnnuel($code));
        }

        return $overviews;
    }
}
