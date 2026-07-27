<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Adp\AdpParser;
use App\Domain\Adp\ImportPlanner;
use App\Entity\EmployerReading;
use App\Entity\PunchEvent;
use App\Entity\RawImport;
use App\Entity\User;
use App\Import\AdpImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Valide l'import de bout en bout contre une vraie base : c'est le seul moyen de
 * prouver l'idempotence, qui repose sur la contrainte d'unicité (user, date, heure, rang).
 */
final class AdpImporterTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AdpImporter $importer;
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

        // AdpImporter n'est encore référencé par aucun contrôleur : le conteneur de
        // test l'inline. On le monte donc à la main avec ses dépendances. L'entité
        // déclare son repositoryClass, donc getRepository rend bien un PunchEventRepository.
        $repository = $entityManager->getRepository(PunchEvent::class);
        $this->importer = new AdpImporter($entityManager, new AdpParser(), new ImportPlanner(), $repository);

        $this->resetSchema();

        $this->user = User::register('guillaume@example.com', 'hashed-password');
        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    #[Test]
    public function it_persists_a_week_of_punches_and_readings(): void
    {
        $plan = $this->importer->import($this->user, $this->paste(), 2026, new \DateTimeImmutable('2026-07-24 03:00:00'));

        self::assertCount(4, $plan->punchesToCreate());
        self::assertSame(4, $this->countRows(PunchEvent::class));
        self::assertSame(2, $this->countRows(EmployerReading::class));
        self::assertSame(1, $this->countRows(RawImport::class));
    }

    #[Test]
    public function re_importing_adds_no_punch_but_appends_a_new_reading_each_time(): void
    {
        $this->importer->import($this->user, $this->paste(), 2026, new \DateTimeImmutable('2026-07-24 03:00:00'));

        $plan = $this->importer->import($this->user, $this->paste(), 2026, new \DateTimeImmutable('2026-07-27 03:00:00'));

        self::assertCount(0, $plan->punchesToCreate());
        self::assertSame(4, $this->countRows(PunchEvent::class));  // pointages inchangés
        self::assertSame(4, $this->countRows(EmployerReading::class)); // relevés doublés
        self::assertSame(2, $this->countRows(RawImport::class));
    }

    /**
     * @param class-string $entity
     */
    private function countRows(string $entity): int
    {
        return $this->entityManager->getRepository($entity)->count([]);
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
            20/07
            7:24h
            Pointage
            08:30
            Pointage
            16:42
            Attendu
            08:30 - 16:42
            21/07
            0:00h
            Pointage
            08:00
            Pointage
            16:12
            Attendu
            08:00 - 16:12
            TXT;
    }
}
