<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class CreateUserCommandTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $entityManager = $container->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \RuntimeException('EntityManager indisponible.');
        }
        $this->entityManager = $entityManager;
        $this->resetSchema();

        $application = new Application(self::$kernel);
        $command = $application->find('app:user:create');
        $this->commandTester = new CommandTester($command);
    }

    #[Test]
    public function it_creates_a_user_with_a_hashed_password(): void
    {
        $this->commandTester->execute([
            'email' => 'guillaume@example.com',
            '--password' => 'un-mot-de-passe-solide',
        ]);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findByEmail('guillaume@example.com');
        self::assertNotNull($user);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'un-mot-de-passe-solide'));
    }

    #[Test]
    public function it_refuses_a_duplicate_email(): void
    {
        $this->commandTester->execute([
            'email' => 'guillaume@example.com',
            '--password' => 'un-mot-de-passe-solide',
        ]);

        $this->commandTester->execute([
            'email' => 'guillaume@example.com',
            '--password' => 'un-autre-mot-de-passe',
        ]);

        self::assertNotSame(0, $this->commandTester->getStatusCode());
        self::assertStringContainsString('existe déjà', $this->commandTester->getDisplay());

        $repository = $this->entityManager->getRepository(User::class);
        self::assertSame(1, $repository->count([]));
    }

    #[Test]
    public function it_prompts_for_the_password_when_the_option_is_omitted(): void
    {
        $this->commandTester->setInputs(['un-mot-de-passe-saisi']);
        $this->commandTester->execute(['email' => 'guillaume@example.com']);

        self::assertSame(0, $this->commandTester->getStatusCode());

        $repository = $this->entityManager->getRepository(User::class);
        $user = $repository->findByEmail('guillaume@example.com');
        self::assertNotNull($user);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        self::assertTrue($hasher->isPasswordValid($user, 'un-mot-de-passe-saisi'));
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
