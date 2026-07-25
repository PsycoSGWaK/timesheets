<?php

declare(strict_types=1);

namespace App\Tests\Domain\Reconciliation;

use App\Domain\Reconciliation\ReconciliationDetector;
use App\Domain\Reconciliation\ReconciliationStatus;
use App\Domain\Time\Minutes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReconciliationDetectorTest extends TestCase
{
    private const TODAY = '2026-07-25';

    #[Test]
    public function matching_totals_on_a_past_day_are_aligned(): void
    {
        $day = $this->reconcile('2026-07-23', 444, 444);

        self::assertSame(ReconciliationStatus::Aligned, $day->status());
        self::assertSame(0, $day->delta()?->value());
        self::assertFalse($day->needsAttention());
    }

    #[Test]
    public function a_gap_on_a_past_day_is_divergent(): void
    {
        // Nous comptons 444, ADP 400 : 44 min d'écart à investiguer.
        $day = $this->reconcile('2026-07-23', 444, 400);

        self::assertSame(ReconciliationStatus::Divergent, $day->status());
        self::assertSame(44, $day->delta()?->value());
        self::assertTrue($day->needsAttention());
    }

    #[Test]
    public function the_delta_can_be_negative_when_adp_counts_more_than_us(): void
    {
        $day = $this->reconcile('2026-07-23', 400, 444);

        self::assertSame(ReconciliationStatus::Divergent, $day->status());
        self::assertSame(-44, $day->delta()?->value());
        self::assertTrue($day->needsAttention());
    }

    #[Test]
    public function a_zero_employer_total_against_our_worked_time_is_the_flagship_case(): void
    {
        // Le cas 0:00 sur jour passé : une journée entière perdue dans le décompte ADP.
        $day = $this->reconcile('2026-07-23', 396, 0);

        self::assertSame(ReconciliationStatus::EmployerZero, $day->status());
        self::assertSame(396, $day->delta()?->value());
        self::assertTrue($day->needsAttention());
    }

    #[Test]
    public function a_genuine_rest_day_where_both_are_zero_is_aligned(): void
    {
        $day = $this->reconcile('2026-07-23', 0, 0);

        self::assertSame(ReconciliationStatus::Aligned, $day->status());
        self::assertFalse($day->needsAttention());
    }

    #[Test]
    public function the_current_day_is_pending_because_adp_has_not_consolidated_yet(): void
    {
        // Un 0:00 sur le jour courant est normal : ADP ne consolide qu'après minuit.
        // On reste silencieux plutôt que d'alerter chaque soir sans motif (§4bis).
        $day = $this->reconcile(self::TODAY, 300, 0);

        self::assertSame(ReconciliationStatus::Pending, $day->status());
        self::assertFalse($day->needsAttention());
    }

    #[Test]
    public function a_future_day_is_pending_too(): void
    {
        $day = $this->reconcile('2026-07-26', 0, 0);

        self::assertSame(ReconciliationStatus::Pending, $day->status());
        self::assertFalse($day->needsAttention());
    }

    #[Test]
    public function a_past_day_without_any_reading_cannot_be_compared(): void
    {
        $day = $this->reconcile('2026-07-23', 444, null);

        self::assertSame(ReconciliationStatus::NoReading, $day->status());
        self::assertNull($day->delta());
        self::assertFalse($day->needsAttention());
    }

    private function reconcile(string $date, int $ourMinutes, ?int $employerMinutes): \App\Domain\Reconciliation\DayReconciliation
    {
        return (new ReconciliationDetector())->reconcile(
            new \DateTimeImmutable($date),
            Minutes::of($ourMinutes),
            null === $employerMinutes ? null : Minutes::of($employerMinutes),
            new \DateTimeImmutable(self::TODAY),
        );
    }
}
