<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsControllerTest extends WebTestCase
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
    public function it_shows_the_default_values_when_nothing_was_ever_saved(): void
    {
        $crawler = $this->client->request('GET', '/parametres');

        self::assertResponseIsSuccessful();
        self::assertSame('00:30', $crawler->filter('input[name="pause_minimale"]')->attr('value'));
        self::assertSame('11:30', $crawler->filter('input[name="fenetre_debut"]')->attr('value'));
        self::assertSame('14:00', $crawler->filter('input[name="fenetre_fin"]')->attr('value'));
        self::assertSame('07:00', $crawler->filter('input[name="journee_reference_contractuelle"]')->attr('value'));
        self::assertSame('07:24', $crawler->filter('input[name="journee_reference_effective"]')->attr('value'));
        self::assertSame('02:00', $crawler->filter('input[name="rtt_max"]')->attr('value'));
        self::assertSame('16:00', $crawler->filter('input[name="fin_apres_midi_teletravail"]')->attr('value'));
    }

    #[Test]
    public function saving_creates_the_settings_row_and_they_are_applied(): void
    {
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:20',
            'fenetre_debut' => '11:00',
            'fenetre_fin' => '15:00',
            'journee_reference_contractuelle' => '07:00',
            'journee_reference_effective' => '07:00',
            'rtt_max' => '03:00',
            'fin_apres_midi_teletravail' => '16:00',
        ]);

        self::assertResponseRedirects('/parametres');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.success', 'enregistré');

        $settings = $this->entityManager->getRepository(Settings::class)->findOneBy([]);
        self::assertNotNull($settings);
        self::assertSame(20, $settings->pauseMinimale()->value());
        self::assertSame(660, $settings->fenetreDebut()->value());
        self::assertSame(420, $settings->journeeReferenceEffective()->value());
        self::assertSame(35 * 60, $settings->weeklyBascule()->value()); // 7h effective x 5
    }

    #[Test]
    public function resaving_updates_the_same_row_instead_of_duplicating(): void
    {
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:20', 'fenetre_debut' => '11:00', 'fenetre_fin' => '15:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:00', 'rtt_max' => '03:00',
            'fin_apres_midi_teletravail' => '16:00',
        ]);
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:15', 'fenetre_debut' => '11:00', 'fenetre_fin' => '15:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:00', 'rtt_max' => '03:00',
            'fin_apres_midi_teletravail' => '16:00',
        ]);

        self::assertSame(1, $this->entityManager->getRepository(Settings::class)->count([]));
        $settings = $this->entityManager->getRepository(Settings::class)->findOneBy([]);
        self::assertNotNull($settings);
        self::assertSame(15, $settings->pauseMinimale()->value());
    }

    #[Test]
    public function an_invalid_break_window_is_rejected_without_saving(): void
    {
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:30', 'fenetre_debut' => '15:00', 'fenetre_fin' => '11:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:24', 'rtt_max' => '02:00',
            'fin_apres_midi_teletravail' => '16:00',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.error');
        self::assertSame(0, $this->entityManager->getRepository(Settings::class)->count([]));
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
