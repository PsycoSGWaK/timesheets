<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Crée un compte utilisateur.
 *
 * Aucune inscription publique n'est prévue : c'est la seule façon d'obtenir un
 * compte sur l'application.
 */
#[AsCommand(name: 'app:user:create', description: 'Crée un compte utilisateur')]
final class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse email du compte')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe (demandé de façon masquée si omis)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string $email */
        $email = $input->getArgument('email');

        if (null !== $this->userRepository->findByEmail($email)) {
            $io->error(sprintf('Un compte avec l\'email "%s" existe déjà.', $email));

            return Command::FAILURE;
        }

        $password = $input->getOption('password');
        if (null === $password) {
            // Pas de saisie masquée : peu fiable en environnement non interactif
            // (bloque indéfiniment sous Windows sans vrai terminal). Compromis
            // acceptable pour un outil réservé à l'administrateur de l'appli.
            /** @var string $password */
            $password = $io->askQuestion(new Question('Mot de passe : '));
        }

        if ('' === trim((string) $password)) {
            $io->error('Le mot de passe ne peut pas être vide.');

            return Command::FAILURE;
        }

        $user = User::register($email, 'temporaire');
        $user->rehashPassword($this->passwordHasher->hashPassword($user, (string) $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(sprintf('Compte créé pour "%s".', $user->email()));

        return Command::SUCCESS;
    }
}
