<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    #[Test]
    public function it_identifies_itself_by_email(): void
    {
        $user = User::register('guillaume@example.com', 'hashed-password');

        self::assertSame('guillaume@example.com', $user->email());
        self::assertSame('guillaume@example.com', $user->getUserIdentifier());
    }

    #[Test]
    public function it_carries_a_hashed_password(): void
    {
        $user = User::register('guillaume@example.com', 'hashed-password');

        self::assertSame('hashed-password', $user->getPassword());
    }

    #[Test]
    public function a_fresh_user_has_the_default_role_only(): void
    {
        $user = User::register('guillaume@example.com', 'hashed-password');

        self::assertSame(['ROLE_USER'], $user->getRoles());
    }

    #[Test]
    public function it_erases_no_transient_credentials(): void
    {
        // Aucun champ en clair a effacer ici, mais l'interface l'exige : ne doit
        // jamais planter, et ne doit surtout pas effacer le mot de passe hache.
        $user = User::register('guillaume@example.com', 'hashed-password');

        $user->eraseCredentials();

        self::assertSame('hashed-password', $user->getPassword());
    }

    #[Test]
    public function it_normalises_the_email_to_lowercase(): void
    {
        $user = User::register('Guillaume@Example.COM', 'hashed-password');

        self::assertSame('guillaume@example.com', $user->email());
    }

    #[Test]
    public function it_has_no_identity_before_persistence(): void
    {
        $user = User::register('guillaume@example.com', 'hashed-password');

        self::assertNull($user->id());
    }

    #[Test]
    public function it_can_rehash_its_password(): void
    {
        $user = User::register('guillaume@example.com', 'old-hash');

        $user->rehashPassword('new-hash');

        self::assertSame('new-hash', $user->getPassword());
    }
}
