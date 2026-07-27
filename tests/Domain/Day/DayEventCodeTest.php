<?php

declare(strict_types=1);

namespace App\Tests\Domain\Day;

use App\Domain\Day\DayEventCode;
use App\Entity\Settings;
use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DayEventCodeTest extends TestCase
{
    private Settings $settings;

    protected function setUp(): void
    {
        $this->settings = Settings::defaults(User::register('guillaume@example.com', 'hashed-password'));
    }

    #[Test]
    public function it_has_the_five_day_based_codes_of_the_specification(): void
    {
        // Spec §2 : les événements exprimés en jours (1 ou 0,5). Les événements en
        // heures (HS, HV, Abs) sont hors périmètre de cette tranche.
        self::assertCount(5, DayEventCode::cases());
    }

    #[Test]
    public function its_backing_values_are_stable_for_persistence(): void
    {
        self::assertSame('CP', DayEventCode::CongePaye->value);
        self::assertSame('CA', DayEventCode::CongeAnciennete->value);
        self::assertSame('RTT', DayEventCode::Rtt->value);
        self::assertSame('JF', DayEventCode::JourFerie->value);
        self::assertSame('TT', DayEventCode::Teletravail->value);
    }

    #[Test]
    public function every_case_carries_a_human_label(): void
    {
        foreach (DayEventCode::cases() as $code) {
            self::assertNotSame('', $code->label());
        }
    }

    #[Test]
    public function teletravail_uses_the_effective_reference_day(): void
    {
        // Le TT est du travail réel : il suit la journée de référence 7h24 (37h/5j),
        // confirmée par l'horaire théorique d'ADP (source-adp §3.2).
        self::assertSame(444, DayEventCode::Teletravail->referenceDay($this->settings)->value());
    }

    #[Test]
    public function absences_use_the_contractual_reference_day(): void
    {
        // CP/CA/RTT/JF ne sont pas du travail : ils se comptent sur la base
        // contractuelle 35h/5j, distincte des 37h effectifs (confirmé par Guillaume).
        self::assertSame(420, DayEventCode::CongePaye->referenceDay($this->settings)->value());
        self::assertSame(420, DayEventCode::CongeAnciennete->referenceDay($this->settings)->value());
        self::assertSame(420, DayEventCode::Rtt->referenceDay($this->settings)->value());
        self::assertSame(420, DayEventCode::JourFerie->referenceDay($this->settings)->value());
    }
}
