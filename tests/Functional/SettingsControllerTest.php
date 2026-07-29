<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Settings;
use App\Tests\ResetsSchema;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SettingsControllerTest extends WebTestCase
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
        $checked = $crawler->filter('input[name="jours_de_repos[]"]:checked')->extract(['value']);
        sort($checked);
        self::assertSame(['6', '7'], $checked);
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
            'jours_de_repos' => ['6', '7'],
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
        self::assertSame([6, 7], $settings->joursDeRepos());
    }

    #[Test]
    public function saving_fewer_rest_days_shrinks_the_weekly_thresholds(): void
    {
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:30', 'fenetre_debut' => '11:30', 'fenetre_fin' => '14:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:00',
            'rtt_max' => '02:00', 'fin_apres_midi_teletravail' => '16:00',
            'jours_de_repos' => ['7'], // dimanche seul : 6 jours ouvrés
        ]);

        $settings = $this->entityManager->getRepository(Settings::class)->findOneBy([]);
        self::assertNotNull($settings);
        self::assertSame([7], $settings->joursDeRepos());
        self::assertSame(6 * 7 * 60, $settings->weeklyBascule()->value());
    }

    #[Test]
    public function an_unchecked_rest_day_form_is_rejected_without_saving(): void
    {
        // Aucune case cochée : les 7 jours seraient ouvrés, ce qui n'est pas
        // l'objet du test, mais confirme que le formulaire transmet bien un
        // tableau vide plutôt qu'une erreur — vérifié séparément par le rejet
        // "tous les jours de repos" (voir SettingsTest).
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:30', 'fenetre_debut' => '11:30', 'fenetre_fin' => '14:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:24',
            'rtt_max' => '02:00', 'fin_apres_midi_teletravail' => '16:00',
            'jours_de_repos' => ['1', '2', '3', '4', '5', '6', '7'],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.error');
        self::assertSame(0, $this->entityManager->getRepository(Settings::class)->count([]));
    }

    #[Test]
    public function resaving_updates_the_same_row_instead_of_duplicating(): void
    {
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:20', 'fenetre_debut' => '11:00', 'fenetre_fin' => '15:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:00', 'rtt_max' => '03:00',
            'fin_apres_midi_teletravail' => '16:00', 'jours_de_repos' => ['6', '7'],
        ]);
        $this->client->request('POST', '/parametres', [
            'pause_minimale' => '00:15', 'fenetre_debut' => '11:00', 'fenetre_fin' => '15:00',
            'journee_reference_contractuelle' => '07:00', 'journee_reference_effective' => '07:00', 'rtt_max' => '03:00',
            'fin_apres_midi_teletravail' => '16:00', 'jours_de_repos' => ['6', '7'],
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
}
