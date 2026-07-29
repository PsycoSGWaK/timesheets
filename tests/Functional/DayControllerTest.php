<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\PunchEvent;
use App\Tests\ResetsSchema;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Enregistrement des pointages prévisionnels/correctifs d'un jour : l'édition
 * elle-même vit dans le panneau intégré à « Ma semaine » ({@see WeekControllerTest}),
 * cette route ne fait plus que sauvegarder puis rediriger vers la semaine du jour
 * concerné (règle du 29/07/2026 — avant, on retombait sur la semaine courante).
 */
final class DayControllerTest extends WebTestCase
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
    public function visiting_the_legacy_day_url_redirects_to_its_week_with_the_day_selected(): void
    {
        // 24/07/2026 tombe semaine ISO 30 de 2026.
        $this->client->request('GET', '/jour/2026-07-24');

        self::assertResponseRedirects('/semaine/2026/30?jour=2026-07-24');
    }

    #[Test]
    public function saving_redirects_to_the_week_being_edited_not_the_current_week(): void
    {
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:48',
            'midi' => '11:47',
            'apres_midi' => '12:13',
            'soir' => '',
        ]);

        // Régression du 29/07/2026 : ramenait auparavant sur la semaine courante.
        self::assertResponseRedirects('/semaine/2026/30?jour=2026-07-24');

        $punches = $this->entityManager->getRepository(PunchEvent::class)->findBy([]);
        self::assertCount(3, $punches);
        foreach ($punches as $punch) {
            self::assertFalse($punch->isProbative());
        }
    }

    #[Test]
    public function after_saving_the_week_screen_shows_the_panel_prefilled_with_the_projection(): void
    {
        $this->client->request('POST', '/jour/2026-07-24', [
            'matin' => '08:48', 'midi' => '11:47', 'apres_midi' => '12:13', 'soir' => '',
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.day-panel .leave', '16:42');
        self::assertSelectorExists('.day-panel input[name="matin"]:not([readonly])');
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

        $this->client->request('GET', '/semaine/2026/30', ['jour' => '2026-07-24']);
        self::assertSelectorTextContains('.day-panel .leave', '16:42');
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

        $this->client->request('GET', '/semaine/2026/30', ['jour' => '2026-07-24']);

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.day-panel input[name="matin"][readonly]');
        self::assertSelectorTextContains('.day-panel .leave', '16:42');

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
