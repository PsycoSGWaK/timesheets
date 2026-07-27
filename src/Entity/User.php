<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Un compte. Toutes les données de l'application (pointages, relevés ADP, imports)
 * appartiennent à un et un seul utilisateur, qui est seul à pouvoir les consulter et
 * les modifier.
 *
 * Pas d'inscription publique : les comptes se créent via la commande console
 * `app:user:create`, pas par un formulaire ouvert sur l'appli.
 */
#[ORM\Entity]
#[ORM\Table(name: 'app_user')]
#[ORM\UniqueConstraint(name: 'uniq_user_email', columns: ['email'])]
final class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private string $email;

    #[ORM\Column]
    private string $password;

    /**
     * @var list<string>
     */
    #[ORM\Column]
    private array $roles = [];

    private function __construct(string $email, string $hashedPassword)
    {
        $this->email = strtolower($email);
        $this->password = $hashedPassword;
        $this->roles = ['ROLE_USER'];
    }

    public static function register(string $email, string $hashedPassword): self
    {
        return new self($email, $hashedPassword);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function email(): string
    {
        return $this->email;
    }

    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    public function getPassword(): string
    {
        return $this->password;
    }

    public function rehashPassword(string $hashedPassword): void
    {
        $this->password = $hashedPassword;
    }

    /**
     * @return list<string>
     */
    public function getRoles(): array
    {
        return $this->roles;
    }

    public function eraseCredentials(): void
    {
        // Aucun champ en clair (mot de passe non hache, code temporaire...) n'est
        // jamais porte par cette entite : rien a effacer. Methode exigee par
        // l'interface, volontairement vide.
    }
}
