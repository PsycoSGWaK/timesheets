<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\DayEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DayEventControllerTest extends WebTestCase
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
    public function it_declares_a_teletravail_day_and_shows_it_in_the_week(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'TT',
            'portion' => 'full',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSame(1, $this->entityManager->getRepository(DayEvent::class)->count([]));
        self::assertSelectorTextContains('body', 'Télétravail');
        // Valorisée par l'événement : plus de 0h00 pour cette journée sans badge.
        self::assertSelectorTextContains('body', '7h24');
    }

    #[Test]
    public function it_replaces_an_existing_event_on_the_same_day(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'TT',
            'portion' => 'full',
        ]);
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'CP',
            'portion' => 'full',
        ]);

        self::assertSame(1, $this->entityManager->getRepository(DayEvent::class)->count([]));
        $event = $this->entityManager->getRepository(DayEvent::class)->findOneBy([]);
        self::assertNotNull($event);
        self::assertSame('CP', $event->code()->value);
    }

    #[Test]
    public function it_removes_an_event(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'TT',
            'portion' => 'full',
        ]);

        $this->client->request('POST', '/semaine/evenement/supprimer', [
            'date' => '2026-07-24',
        ]);

        self::assertResponseRedirects();
        self::assertSame(0, $this->entityManager->getRepository(DayEvent::class)->count([]));
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
}
