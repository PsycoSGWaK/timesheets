<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EmployerReading;
use App\Entity\PunchEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ImportControllerTest extends WebTestCase
{
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
    }

    #[Test]
    public function the_form_is_reachable(): void
    {
        $this->client->request('GET', '/import');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('textarea[name="payload"]');
    }

    #[Test]
    public function the_home_page_redirects_to_the_import_screen(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/import');
    }

    #[Test]
    public function previewing_shows_the_parsed_days_without_writing_anything(): void
    {
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'apercu',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2', 'Ce qui a été compris');
        self::assertSame(0, $this->entityManager->getRepository(PunchEvent::class)->count([]));
    }

    #[Test]
    public function confirming_imports_the_week_into_the_database(): void
    {
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'importer',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.result', 'Import effectué');
        self::assertSame(4, $this->entityManager->getRepository(PunchEvent::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(EmployerReading::class)->count([]));
    }

    private function resetSchema(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['punch_event', 'employer_reading', 'raw_import'] as $table) {
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
            16:42
            Attendu
            08:30 - 16:42
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
