<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DailyAnomaly;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DailyAnomalyTest extends TestCase
{
    #[Test]
    public function its_backing_values_are_stable(): void
    {
        self::assertSame('badgeage_manquant', DailyAnomaly::BadgeageManquant->value);
        self::assertSame('pause_hors_fenetre', DailyAnomaly::PauseHorsFenetre->value);
        self::assertSame('pause_trop_courte', DailyAnomaly::PauseTropCourte->value);
        self::assertSame('intervalle_negatif', DailyAnomaly::IntervalleNegatif->value);
    }

    #[Test]
    public function every_case_carries_a_human_label(): void
    {
        foreach (DailyAnomaly::cases() as $anomaly) {
            self::assertNotSame('', $anomaly->label());
        }
    }
}
