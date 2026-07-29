<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayPortion;
use App\Entity\DayEvent;
use App\Entity\Settings;
use App\Entity\User;
use App\Tests\ResetsSchema;
use App\Week\EventQuotaLoader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * EventQuotaLoader n'est qu'un branchement (un dépôt, une somme de portions déjà
 * testée via {@see \App\Tests\Domain\Day\DayPortionTest}) : ce test prouve
 * seulement le câblage contre une vraie base, dont le filtre par année civile.
 */
final class EventQuotaLoaderTest extends KernelTestCase
{
    use ResetsSchema;

    private EntityManagerInterface $entityManager;
    private EventQuotaLoader $loader;
    private User $user;
    private Settings $settings;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManager indisponible.');
        }
        $this->entityManager = $entityManager;

        $this->resetSchema($this->entityManager);

        $this->user = User::register('guillaume@example.com', 'hashed-password');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();

        $this->loader = new EventQuotaLoader($this->entityManager->getRepository(DayEvent::class));

        $this->settings = Settings::defaults($this->user);
        $this->settings->update(
            pauseMinimale: 30,
            fenetreDebut: 11 * 60 + 30,
            fenetreFin: 14 * 60,
            journeeReferenceContractuelle: 7 * 60,
            journeeReferenceEffective: 7 * 60 + 24,
            rttMax: 2 * 60,
            finApresMidiTeletravail: 16 * 60,
            joursDeRepos: [6, 7],
            quotasAnnuels: ['CP' => 50], // 25 j
        );
    }

    #[Test]
    public function it_sums_full_and_half_days_declared_within_the_year(): void
    {
        $this->entityManager->persist(DayEvent::declare($this->user, new \DateTimeImmutable('2026-03-10'), DayEventCode::CongePaye, DayPortion::Full));
        $this->entityManager->persist(DayEvent::declare($this->user, new \DateTimeImmutable('2026-03-11'), DayEventCode::CongePaye, DayPortion::Half));
        $this->entityManager->flush();

        $overviews = $this->loader->load($this->user, $this->settings, 2026);

        $cp = $this->overviewFor($overviews, DayEventCode::CongePaye);
        self::assertSame(3, $cp->used()->halfDays()); // 1,5 j
        self::assertSame(50, $cp->quota()->halfDays());
        self::assertSame(47, $cp->remaining()->halfDays());
    }

    #[Test]
    public function events_from_another_year_are_not_counted(): void
    {
        $this->entityManager->persist(DayEvent::declare($this->user, new \DateTimeImmutable('2025-12-31'), DayEventCode::CongePaye, DayPortion::Full));
        $this->entityManager->flush();

        $overviews = $this->loader->load($this->user, $this->settings, 2026);

        self::assertSame(0, $this->overviewFor($overviews, DayEventCode::CongePaye)->used()->halfDays());
    }

    /**
     * @param list<\App\Domain\Day\EventQuotaOverview> $overviews
     */
    private function overviewFor(array $overviews, DayEventCode $code): \App\Domain\Day\EventQuotaOverview
    {
        foreach ($overviews as $overview) {
            if ($overview->code() === $code) {
                return $overview;
            }
        }

        throw new \RuntimeException(sprintf('Aucun décompte pour %s.', $code->value));
    }
}
