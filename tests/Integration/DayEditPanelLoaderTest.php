<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Projection\LeaveTimeCalculator;
use App\Domain\Time\Minutes;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use App\Tests\ResetsSchema;
use App\Week\DayEditPanelLoader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * DayEditPanelLoader n'est qu'un branchement (dépôt + LeaveTimeCalculator, déjà
 * testé indépendamment) : ce test prouve seulement le câblage contre une vraie
 * base, pas la logique de calcul de l'estimation de départ.
 */
final class DayEditPanelLoaderTest extends KernelTestCase
{
    use ResetsSchema;

    private EntityManagerInterface $entityManager;
    private DayEditPanelLoader $loader;
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
        $this->settings = Settings::defaults($this->user);

        $this->loader = new DayEditPanelLoader(
            $this->entityManager->getRepository(PunchEvent::class),
            new LeaveTimeCalculator(),
        );
    }

    #[Test]
    public function a_fresh_day_has_four_empty_editable_slots_and_no_estimate(): void
    {
        $panel = $this->loader->load($this->user, new \DateTimeImmutable('2026-07-24'), $this->settings);

        self::assertCount(4, $panel->slots());
        foreach ($panel->slots() as $slot) {
            self::assertSame('', $slot['value']);
            self::assertFalse($slot['readonly']);
        }
        self::assertNull($panel->estimate());
    }

    #[Test]
    public function a_day_with_three_punches_exposes_them_and_an_estimate(): void
    {
        $day = new \DateTimeImmutable('2026-07-24');
        $this->entityManager->persist(PunchEvent::realFromAdp($this->user, $day, Minutes::fromClock('08:30'), 1));
        $this->entityManager->persist(PunchEvent::realFromAdp($this->user, $day, Minutes::fromClock('12:00'), 2));
        $this->entityManager->persist(PunchEvent::realFromAdp($this->user, $day, Minutes::fromClock('12:30'), 3));
        $this->entityManager->flush();

        $panel = $this->loader->load($this->user, $day, $this->settings);

        self::assertSame('08:30', $panel->slots()[0]['value']);
        self::assertTrue($panel->slots()[0]['readonly']);
        self::assertSame('', $panel->slots()[3]['value']); // soir, pas encore pointé
        self::assertNotNull($panel->estimate());
    }
}
