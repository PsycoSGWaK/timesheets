<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260729215406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le quota annuel de jours poses par code CP/TT/RTT/JF (spec du 29/07/2026).';
    }

    public function up(Schema $schema): void
    {
        // Defaut '{}' (aucun quota configure) pour les lignes deja en base, comme
        // Settings::defaults() : une ligne existante ne doit pas planter au chargement.
        $this->addSql("ALTER TABLE settings ADD quotas_annuels JSON NOT NULL DEFAULT '{}', CHANGE jours_de_repos jours_de_repos JSON NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP quotas_annuels, CHANGE jours_de_repos jours_de_repos JSON DEFAULT \'[6, 7]\' NOT NULL');
    }
}
