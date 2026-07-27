<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727202047 extends AbstractMigration
{
    public function getDescription(): string
    {
        return "Ajoute les jours de repos configurables (spec du 28/07/2026, defaut samedi+dimanche).";
    }

    public function up(Schema $schema): void
    {
        // Defaut '[6, 7]' (samedi+dimanche) pour les lignes deja en base, comme
        // Settings::defaults() : une ligne existante ne doit pas se retrouver a
        // zero jour de repos.
        $this->addSql("ALTER TABLE settings ADD jours_de_repos JSON NOT NULL DEFAULT '[6, 7]', CHANGE fin_apres_midi_teletravail fin_apres_midi_teletravail INT NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE settings DROP jours_de_repos, CHANGE fin_apres_midi_teletravail fin_apres_midi_teletravail INT DEFAULT 960 NOT NULL');
    }
}
