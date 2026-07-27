<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WeekControllerTest extends WebTestCase
{
    use LogsInAUser;

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

        $this->resetSchema();
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

    private function resetSchema(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['punch_event', 'employer_reading', 'raw_import', 'day_event', 'app_user'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tool = new SchemaTool($this->entityManager);
        $tool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
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
