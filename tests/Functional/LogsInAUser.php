<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;

/**
 * Authentifie un utilisateur de test dans le client de navigation. `loginUser`
 * ne vérifie pas de mot de passe : un compte minimal suffit.
 */
trait LogsInAUser
{
    private function logIn(KernelBrowser $client, EntityManagerInterface $entityManager): User
    {
        $user = User::register('test@example.com', 'unused-hash');

        $entityManager->persist($user);
        $entityManager->flush();

        $client->loginUser($user);

        return $user;
    }
}
