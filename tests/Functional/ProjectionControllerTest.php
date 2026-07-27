<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ProjectionControllerTest extends WebTestCase
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
    public function the_form_is_reachable(): void
    {
        $this->client->request('GET', '/quand-partir');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="morning_start"]');
    }

    #[Test]
    public function it_computes_the_leave_time_from_the_morning(): void
    {
        $this->client->request('POST', '/quand-partir', [
            'morning_start' => '08:48',
            'lunch_departure' => '11:47',
            'lunch_return' => '12:13',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.leave', '16:42');
    }

    #[Test]
    public function an_unreadable_time_is_reported_without_crashing(): void
    {
        $this->client->request('POST', '/quand-partir', [
            'morning_start' => 'midi',
            'lunch_departure' => '11:47',
            'lunch_return' => '12:13',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.error');
    }

    private function resetSchema(): void
    {
        $connection = $this->entityManager->getConnection();
        foreach (['punch_event', 'employer_reading', 'raw_import', 'day_event', 'balance_movement', 'settings', 'app_user'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tool = new SchemaTool($this->entityManager);
        $tool->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());
    }
}
