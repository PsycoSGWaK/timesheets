<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

final class SecurityTest extends WebTestCase
{
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
    }

    #[Test]
    public function an_anonymous_visitor_is_redirected_to_the_login_page(): void
    {
        $this->client->request('GET', '/import');

        self::assertResponseRedirects('/login');
    }

    #[Test]
    public function the_login_page_itself_is_reachable_anonymously(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="email"]');
        self::assertSelectorExists('input[name="password"]');
    }

    #[Test]
    public function a_registered_user_can_log_in_and_reach_the_app(): void
    {
        $this->createUser('guillaume@example.com', 'un-mot-de-passe-solide');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            'email' => 'guillaume@example.com',
            'password' => 'un-mot-de-passe-solide',
        ]);

        self::assertResponseRedirects('/semaine');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function a_wrong_password_is_rejected(): void
    {
        $this->createUser('guillaume@example.com', 'un-mot-de-passe-solide');

        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            'email' => 'guillaume@example.com',
            'password' => 'mauvais-mot-de-passe',
        ]);

        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorExists('.error');
    }

    #[Test]
    public function a_logged_in_user_can_log_out(): void
    {
        $user = $this->createUser('guillaume@example.com', 'un-mot-de-passe-solide');
        $this->client->loginUser($user);

        $this->client->request('GET', '/semaine');
        self::assertResponseIsSuccessful();

        $this->client->request('GET', '/logout');
        self::assertResponseRedirects();

        $this->client->request('GET', '/import');
        self::assertResponseRedirects('/login');
    }

    private function createUser(string $email, string $plainPassword): User
    {
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        if (!$hasher instanceof UserPasswordHasherInterface) {
            throw new \RuntimeException('UserPasswordHasherInterface indisponible.');
        }

        $user = User::register($email, 'placeholder');
        $user->rehashPassword($hasher->hashPassword($user, $plainPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
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
