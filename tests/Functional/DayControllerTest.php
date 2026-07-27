<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PunchEvent;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * L'écran d'un jour : saisir/modifier les pointages prévisionnels, avec la
 * projection « quand partir » qui apparaît dès que matin/midi/après-midi sont
 * connus (spec §4.6, l'usage principal de l'application).
 */
final class DayControllerTest extends WebTestCase
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
    public function a_fresh_day_shows_four_empty_editable_fields_and_no_projection(): void
    {
        $this->client->request('GET', '/jour/2026-07-24');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="matin"]:not([readonly])');
        self::assertSelectorExists('input[name="midi"]:not([readonly])');
        self::assertSelectorExists('input[name="apres_midi"]:not([readonly])');
        self::assertSelectorExists('input[name="soir"]:not([readonly])');
        self::assertSelectorNotExists('.leave');
    }

    #[Test]
    public function saving_three_punches_creates_provisional_events_and_shows_the_projection(): void
    {
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:48',
            'midi' => '11:47',
            'apres_midi' => '12:13',
            'soir' => '',
        ]);

        self::assertResponseRedirects('/jour/2026-07-24');
        $this->client->followRedirect();

        self::assertSelectorTextContains('.leave', '16:42');

        $punches = $this->entityManager->getRepository(PunchEvent::class)->findBy([]);
        self::assertCount(3, $punches);
        foreach ($punches as $punch) {
            self::assertFalse($punch->isProbative());
        }
    }

    #[Test]
    public function resaving_replaces_the_previous_provisional_punches_instead_of_duplicating(): void
    {
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:00', 'midi' => '12:00', 'apres_midi' => '12:30', 'soir' => '',
        ]);
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:48', 'midi' => '11:47', 'apres_midi' => '12:13', 'soir' => '',
        ]);

        $punches = $this->entityManager->getRepository(PunchEvent::class)->findBy([]);
        self::assertCount(3, $punches);

        $this->client->request('GET', '/jour/2026-07-24');
        self::assertSelectorTextContains('.leave', '16:42');
    }

    #[Test]
    public function real_punches_are_shown_read_only_and_are_never_overwritten(): void
    {
        // Une journée déjà collée depuis ADP : matin/midi/après-midi réels.
        $this->client->request('POST', '/import', [
            'payload' => $this->paste(),
            'year' => 2026,
            'action' => 'importer',
        ]);
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/jour/2026-07-24');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="matin"][readonly]');
        self::assertSelectorTextContains('.leave', '16:42');

        // Tenter de resaisir le matin (déjà réel) ne doit rien casser ni le dupliquer.
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '09:00', 'midi' => '11:47', 'apres_midi' => '12:13', 'soir' => '',
        ]);

        $realCount = 0;
        foreach ($this->entityManager->getRepository(PunchEvent::class)->findBy([]) as $punch) {
            if ($punch->isProbative()) {
                ++$realCount;
                if (1 === $punch->rang()) {
                    self::assertSame(528, $punch->time()->value()); // 08:48, inchangé malgré la tentative
                }
            }
        }
        self::assertSame(3, $realCount);
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
            24/07
            7:24h
            Pointage
            08:48
            Pointage
            11:47
            Pointage
            12:13
            Attendu
            08:30 - 16:42
            TXT;
    }
}
