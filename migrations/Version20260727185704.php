<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727185704 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute le choix matin/apres-midi d'un TT en demi-journee et la fin d'apres-midi TT reglable (regle precise du 28/07/2026).";
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE day_event ADD half VARCHAR(255) DEFAULT NULL');
        // Defaut 960 (16h00, valeur par defaut de Settings::defaults()) pour les
        // lignes deja en base : une ligne existante ne doit pas se retrouver a 0.
        $this->addSql('ALTER TABLE settings ADD fin_apres_midi_teletravail INT NOT NULL DEFAULT 960');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE day_event DROP half');
        $this->addSql('ALTER TABLE settings DROP fin_apres_midi_teletravail');
    }
}
