<?php

declare(strict_types=1);

namespace App\Tests;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * Recrée le schéma de test à partir des entités actuelles, table par table —
 * chaque test part d'une base vide plutôt que des migrations, pour rester
 * indépendant de leur ordre d'application.
 */
trait ResetsSchema
{
    private function resetSchema(EntityManagerInterface $entityManager): void
    {
        $connection = $entityManager->getConnection();
        foreach (['punch_event', 'employer_reading', 'raw_import', 'day_event', 'balance_movement', 'settings', 'app_user'] as $table) {
            $connection->executeStatement('DROP TABLE IF EXISTS '.$table);
        }

        $tool = new SchemaTool($entityManager);
        $tool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
}
