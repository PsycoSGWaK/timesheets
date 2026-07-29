<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\ResetsSchema;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WeekControllerTest extends WebTestCase
{
    use LogsInAUser;
    use ResetsSchema;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManager indisponible.');
        }
        $this->entityManager = $entityManager;

        $this->resetSchema($this->entityManager);
        $this->logIn($this->client, $this->entityManager);
    }

    #[Test]
    public function an_empty_week_still_renders_seven_days(): void
    {
        $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        // 7 lignes de jours dans le corps du tableau.
        self::assertSelectorCount(7, '.week-table tbody tr');
    }

    #[Test]
    public function it_shows_our_recomputation_against_adp_after_an_import(): void
    {
        // On importe d'abord une semaine via l'écran de collage…
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'importer',
        ]);
        self::assertResponseIsSuccessful();

        // … puis on l'affiche. La semaine ISO du 20/07/2026.
        $week = (int) (new \DateTimeImmutable('2026-07-20'))->format('W');
        $this->client->request('GET', '/semaine/2026/'.$week);

        self::assertResponseIsSuccessful();
        // Notre recalcul de la journée de référence, et le 0:00 relevé chez ADP le mardi.
        self::assertSelectorTextContains('body', '7h24');
        self::assertSelectorTextContains('body', '0h00');
    }

    #[Test]
    public function it_shows_both_the_35h_and_37h_weekly_targets(): void
    {
        $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '.projection-target');
        self::assertSelectorTextContains('.projection-target--reference .projection-target__label', '35h');
        self::assertSelectorTextContains('.projection-target--bascule .projection-target__label', '37h');
    }

    #[Test]
    public function no_day_panel_is_shown_without_a_selected_day(): void
    {
        $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.day-panel');
    }

    #[Test]
    public function selecting_a_day_via_the_query_parameter_shows_its_edit_panel(): void
    {
        $this->client->request('GET', '/semaine/2026/30', ['jour' => '2026-07-20']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.day-panel');
        self::assertSelectorTextContains('.day-panel h2', '20/07/2026');
        self::assertSelectorExists('.day-panel input[name="matin"]:not([readonly])');
    }

    #[Test]
    public function an_unreadable_day_query_parameter_is_ignored_rather_than_erroring(): void
    {
        $this->client->request('GET', '/semaine/2026/30', ['jour' => 'n-importe-quoi']);

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.day-panel');
    }

    #[Test]
    public function each_day_row_links_to_its_own_week_with_the_day_selected(): void
    {
        $crawler = $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        $href = $crawler->filter('.week-table tbody tr')->first()->filter('a')->attr('href');
        self::assertSame('/semaine/2026/30?jour=2026-07-20', $href);
    }

    #[Test]
    public function the_balances_panel_shows_all_four_counters(): void
    {
        $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.balances-panel', 'RTT');
        self::assertSelectorTextContains('.balances-panel', 'Récupération');
        self::assertSelectorTextContains('.balances-panel', 'Variable');
        self::assertSelectorTextContains('.balances-panel', 'Paiement');
        self::assertSelectorCount(4, '.balances-panel tbody tr');
    }

    #[Test]
    public function the_balances_panel_reflects_a_credited_rtt(): void
    {
        // 20/07 au 24/07 : 5 jours à 7h24 (37h) -> 2h de RTT acquis (plafond).
        $this->client->request('POST', '/import', [
            'payload' => $this->fullWeekPaste(),
            'year' => 2026,
            'action' => 'importer',
        ]);
        $this->client->request('POST', '/semaine/2026/30/rtt');

        $crawler = $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.balances-panel', '2h00');
    }

    #[Test]
    public function it_jumps_directly_to_the_week_picked_in_the_native_picker(): void
    {
        // <input type="week"> soumet un format YYYY-Www.
        $this->client->request('GET', '/semaine/aller', ['semaine' => '2026-W30']);

        self::assertResponseRedirects('/semaine/2026/30');
    }

    #[Test]
    public function an_unreadable_week_value_falls_back_to_the_current_week(): void
    {
        $this->client->request('GET', '/semaine/aller', ['semaine' => 'n-importe-quoi']);

        self::assertResponseRedirects('/semaine');
    }

    #[Test]
    public function the_picker_is_present_and_prefilled_with_the_displayed_week(): void
    {
        $crawler = $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSame('2026-W30', $crawler->filter('input[name="semaine"]')->attr('value'));
    }

    private function fullWeekPaste(): string
    {
        $days = ['20/07', '21/07', '22/07', '23/07', '24/07'];
        $blocks = array_map(static fn (string $day): string => <<<TXT
            {$day}
            7:24h
            Pointage
            08:30
            Pointage
            12:12
            Pointage
            13:00
            Pointage
            16:42
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            TXT, $days);

        return implode("\n", $blocks);
    }

    private function paste(): string
    {
        return <<<TXT
            20/07
            7:24h
            Pointage
            08:30
            Pointage
            12:12
            Pointage
            13:00
            Pointage
            16:42
            Attendu
            08:30 - 12:12
            13:00 - 16:42
            21/07
            0:00h
            Pointage
            08:00
            Pointage
            16:12
            Attendu
            08:00 - 16:12
            TXT;
    }
}
