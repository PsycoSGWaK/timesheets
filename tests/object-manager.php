<?php

declare(strict_types=1);

// Fournit l'EntityManager à phpstan-doctrine, qui lit ainsi les métadonnées de
// mapping pour comprendre comment l'ORM peuple les entités (identifiants générés,
// colonnes, associations). Sert uniquement à l'analyse statique.

use App\Kernel;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$doctrine = $kernel->getContainer()->get('doctrine');
if (!$doctrine instanceof ManagerRegistry) {
    throw new \RuntimeException('Le registre Doctrine est indisponible.');
}

return $doctrine->getManager();
