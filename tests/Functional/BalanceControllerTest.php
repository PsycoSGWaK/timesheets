<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Balance\BalanceCounter;
use App\Repository\BalanceMovementRepository;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class BalanceControllerTest extends WebTestCase
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
    public function it_shows_all_balances_at_zero_with_nothing_recorded(): void
    {
        $this->client->request('GET', '/compteurs');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'RTT');
        self::assertSelectorTextContains('body', 'Récupération');
        self::assertSelectorTextContains('body', 'Variable');
        self::assertSelectorTextContains('body', 'Paiement');
    }

    #[Test]
    public function crediting_a_weeks_rtt_uses_the_recomputed_amount_not_a_client_supplied_one(): void
    {
        // Semaine pleine à 7h24/jour x 5 = 37h : 2h de RTT (plafond), spec §4.3.
        $this->importFullWeek();

        $this->client->request('POST', '/semaine/2026/30/rtt', ['minutes_falsifie' => '99999']);

        self::assertResponseRedirects();
        self::assertSame(120, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function crediting_the_same_week_twice_replaces_instead_of_doubling(): void
    {
        $this->importFullWeek();

        $this->client->request('POST', '/semaine/2026/30/rtt');
        $this->client->request('POST', '/semaine/2026/30/rtt');

        self::assertSame(120, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function allocating_overtime_splits_it_across_destinies_with_a_motif(): void
    {
        $this->importWeekWithThirtyMinutesOfOvertime(); // 37h30 -> 30 min d'heures sup au-delà de 37h

        $this->client->request('POST', '/semaine/2026/30/heures-sup', [
            'recuperation' => '00:30',
            'variable' => '00:00',
            'paiement' => '00:00',
            'motif' => 'Test allocation',
        ]);

        self::assertResponseRedirects();
        self::assertSame(30, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Recuperation)->value());
    }

    #[Test]
    public function allocating_more_than_the_weeks_overtime_is_rejected(): void
    {
        $this->importFullWeek(); // 0h d'heures sup (semaine à 37h pile)

        $this->client->request('POST', '/semaine/2026/30/heures-sup', [
            'recuperation' => '01:00',
            'variable' => '00:00',
            'paiement' => '00:00',
            'motif' => '',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorExists('.error');
        self::assertSame(0, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Recuperation)->value());
    }

    private function importFullWeek(): void
    {
        // 5 jours à 08:30-12:12 / 13:00-16:42 = 7h24, semaine ISO 30 de 2026 -> 37h pile.
        $this->client->request('POST', '/import', [
            'payload' => <<<TXT
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
                08:30 - 16:42
                21/07
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
                08:30 - 16:42
                22/07
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
                08:30 - 16:42
                23/07
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
                08:30 - 16:42
                24/07
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
                08:30 - 16:42
                TXT,
            'year' => 2026,
            'action' => 'importer',
        ]);
    }

    private function importWeekWithThirtyMinutesOfOvertime(): void
    {
        // Comme importFullWeek, mais le dernier jour finit à 17:12 au lieu de 16:42 :
        // 37h30 sur la semaine, soit 30 min au-delà du seuil de bascule 37h.
        $this->client->request('POST', '/import', [
            'payload' => <<<TXT
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
                08:30 - 16:42
                21/07
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
                08:30 - 16:42
                22/07
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
                08:30 - 16:42
                23/07
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
                08:30 - 16:42
                24/07
                7:54h
                Pointage
                08:30
                Pointage
                12:12
                Pointage
                13:00
                Pointage
                17:12
                Attendu
                08:30 - 17:12
                TXT,
            'year' => 2026,
            'action' => 'importer',
        ]);
    }

    private function balanceRepository(): BalanceMovementRepository
    {
        $repository = static::getContainer()->get(BalanceMovementRepository::class);
        if (!$repository instanceof BalanceMovementRepository) {
            throw new \RuntimeException('BalanceMovementRepository indisponible.');
        }

        return $repository;
    }

    private function currentUser(): User
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy([]);
        if (null === $user) {
            throw new \RuntimeException('Aucun utilisateur en base.');
        }

        return $user;
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
