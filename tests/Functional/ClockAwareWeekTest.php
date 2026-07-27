<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MockClock;

/**
 * Prouve que « aujourd'hui » est piloté par l'horloge injectée
 * ({@see ClockInterface}) et non par l'horloge système : sans ça, la
 * consolidation ADP et la projection hebdomadaire ne peuvent pas être testées
 * de façon déterministe (spec §4bis — un jour n'est comparable que passé).
 */
final class ClockAwareWeekTest extends WebTestCase
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
    public function the_current_week_route_follows_the_mocked_clock_not_the_system_one(): void
    {
        // Mercredi 2026-07-22 imposé par l'horloge : "Ma semaine" doit rendre la
        // semaine ISO 30, quelle que soit la date réelle du système qui exécute le test.
        static::getContainer()->set(ClockInterface::class, new MockClock('2026-07-22'));

        $this->client->request('GET', '/semaine');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Semaine 30');
    }

    private function resetSchema(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['punch_event', 'employer_reading', 'raw_import', 'day_event', 'settings', 'app_user'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tool = new SchemaTool($this->entityManager);
        $tool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }
}
