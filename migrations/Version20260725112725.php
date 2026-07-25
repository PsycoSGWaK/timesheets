<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260725112725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Schéma initial : punch_event (avec unicité date/time/rang), employer_reading, raw_import.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE employer_reading (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, employer_minutes INT NOT NULL, observed_at DATETIME NOT NULL, INDEX idx_reading_date (date), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE punch_event (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, time INT NOT NULL, rang SMALLINT NOT NULL, nature VARCHAR(255) NOT NULL, origin VARCHAR(255) NOT NULL, UNIQUE INDEX uniq_punch_slot (date, time, rang), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE raw_import (id INT AUTO_INCREMENT NOT NULL, raw_payload LONGTEXT NOT NULL, year SMALLINT NOT NULL, imported_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE employer_reading');
        $this->addSql('DROP TABLE punch_event');
        $this->addSql('DROP TABLE raw_import');
    }
}
