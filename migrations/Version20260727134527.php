<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727134527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table balance_movement (compteurs RTT / Recuperation / Variable / Paiement, append-only).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE balance_movement (id INT AUTO_INCREMENT NOT NULL, counter VARCHAR(255) NOT NULL, amount INT NOT NULL, motif LONGTEXT DEFAULT NULL, date DATE NOT NULL, recorded_at DATETIME NOT NULL, user_id INT NOT NULL, INDEX IDX_DF5D2447A76ED395 (user_id), INDEX idx_balance_user_counter (user_id, counter), PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE balance_movement ADD CONSTRAINT FK_DF5D2447A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE balance_movement DROP FOREIGN KEY FK_DF5D2447A76ED395');
        $this->addSql('DROP TABLE balance_movement');
    }
}
