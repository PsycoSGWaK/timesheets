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
        self::assertSelectorCount(7, 'tbody tr');
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
