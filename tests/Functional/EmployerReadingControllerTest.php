<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\EmployerReading;
use App\Entity\PunchEvent;
use App\Tests\ResetsSchema;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmployerReadingControllerTest extends WebTestCase
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
    public function it_removes_the_days_reading_without_touching_its_punches(): void
    {
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'importer',
        ]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->entityManager->getRepository(EmployerReading::class)->count([]));

        $this->client->request('POST', '/semaine/adp/supprimer', ['date' => '2026-07-24']);

        self::assertResponseRedirects('/semaine/2026/30');
        self::assertSame(0, $this->entityManager->getRepository(EmployerReading::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(PunchEvent::class)->count([]));
    }

    #[Test]
    public function the_week_screen_shows_no_button_when_the_day_has_no_reading(): void
    {
        $crawler = $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.adp-remove-btn');
    }

    #[Test]
    public function the_week_screen_shows_the_button_once_a_reading_exists(): void
    {
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'importer',
        ]);

        $crawler = $this->client->request('GET', '/semaine/2026/30');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.adp-remove-btn');
    }

    private function paste(): string
    {
        return <<<TXT
            24/07
            7:24h
            Pointage
            08:48
            Pointage
            16:42
            Attendu
            08:30 - 16:42
            TXT;
    }
}
