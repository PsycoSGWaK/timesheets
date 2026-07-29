<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Day\DailyCalculator;
use App\Domain\Day\DayEventValorizer;
use App\Domain\Reconciliation\ReconciliationDetector;
use App\Domain\Time\Minutes;
use App\Domain\Week\IsoWeek;
use App\Domain\Week\WeeklyCalculator;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\DayEvent;
use App\Entity\EmployerReading;
use App\Entity\PunchEvent;
use App\Entity\Settings;
use App\Entity\User;
use App\Tests\ResetsSchema;
use App\Week\WeekLoader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * WeekLoader n'est qu'un branchement (3 dépôts + WorkWeekAssembler, déjà testés
 * indépendamment) : ce test prouve seulement le câblage contre une vraie base,
 * pas la logique de calcul.
 */
final class WeekLoaderTest extends KernelTestCase
{
    use ResetsSchema;

    private EntityManagerInterface $entityManager;
    private WeekLoader $loader;
    private User $user;

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

        $this->loader = new WeekLoader(
            $this->entityManager->getRepository(PunchEvent::class),
            $this->entityManager->getRepository(EmployerReading::class),
            $this->entityManager->getRepository(DayEvent::class),
            new WorkWeekAssembler(new DailyCalculator(), new WeeklyCalculator(), new ReconciliationDetector(), new DayEventValorizer()),
        );
    }

    #[Test]
    public function it_assembles_a_week_from_the_punches_actually_in_base(): void
    {
        $monday = new \DateTimeImmutable('2026-07-20');
        foreach (['08:30', '12:12', '13:00', '16:42'] as $rang => $clock) {
            $this->entityManager->persist(PunchEvent::realFromAdp($this->user, $monday, Minutes::fromClock($clock), $rang + 1));
        }
        $this->entityManager->flush();

        $dates = IsoWeek::dates(2026, 30);
        $week = $this->loader->load($this->user, $dates, new \DateTimeImmutable('2026-07-25'), Settings::defaults($this->user));

        self::assertSame(444, $week->days()[0]->dayFact()->workedMinutes()->value());
        self::assertSame(444, $week->weekFact()->workedMinutes()->value());
    }

}
