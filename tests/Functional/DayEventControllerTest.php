<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Domain\Balance\BalanceCounter;
use App\Entity\DayEvent;
use App\Repository\BalanceMovementRepository;
use App\Tests\ResetsSchema;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DayEventControllerTest extends WebTestCase
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
    public function it_declares_a_teletravail_morning_half_day_using_times_entered_beforehand(): void
    {
        // Horaires saisis via /jour/{date} avant l'événement (règle précise du
        // 28/07/2026) : arrivée 08:30, retour de pause 12:30 -> 4h de travail.
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:30',
            'apres_midi' => '12:30',
        ]);

        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'TT',
            'portion' => 'half',
            'half' => 'matin',
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();

        self::assertSelectorTextContains('body', '4h00');
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

    #[Test]
    public function declaring_a_full_rtt_day_debits_the_rtt_balance(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'RTT',
            'portion' => 'full',
        ]);

        self::assertResponseRedirects();

        self::assertSame(-420, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function declaring_a_half_rtt_day_debits_half_the_reference_day(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'RTT',
            'portion' => 'half',
        ]);

        self::assertSame(-210, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function removing_an_rtt_day_reverses_its_debit(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'RTT',
            'portion' => 'full',
        ]);

        $this->client->request('POST', '/semaine/evenement/supprimer', [
            'date' => '2026-07-24',
        ]);

        self::assertSame(0, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function replacing_an_rtt_day_with_another_code_reverses_the_debit_without_creating_a_new_one(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'RTT',
            'portion' => 'full',
        ]);
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'CP',
            'portion' => 'full',
        ]);

        self::assertSame(0, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    #[Test]
    public function declaring_a_non_rtt_event_never_touches_the_rtt_balance(): void
    {
        $this->client->request('POST', '/semaine/evenement', [
            'date' => '2026-07-24',
            'code' => 'TT',
            'portion' => 'full',
        ]);

        self::assertSame(0, $this->balanceRepository()->balanceFor($this->currentUser(), BalanceCounter::Rtt)->value());
    }

    private function balanceRepository(): BalanceMovementRepository
    {
        $repository = static::getContainer()->get(BalanceMovementRepository::class);
        if (!$repository instanceof BalanceMovementRepository) {
            throw new \RuntimeException('BalanceMovementRepository indisponible.');
        }

        return $repository;
    }

    private function currentUser(): \App\Entity\User
    {
        $user = $this->entityManager->getRepository(\App\Entity\User::class)->findOneBy([]);
        if (null === $user) {
            throw new \RuntimeException('Aucun utilisateur en base.');
        }

        return $user;
    }
}
