<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727130737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table settings (parametrage par utilisateur : pause, fenetre, journees de reference, RTT max).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE settings (id INT AUTO_INCREMENT NOT NULL, pause_minimale INT NOT NULL, fenetre_debut INT NOT NULL, fenetre_fin INT NOT NULL, journee_reference_contractuelle INT NOT NULL, journee_reference_effective INT NOT NULL, rtt_max INT NOT NULL, user_id INT NOT NULL, UNIQUE INDEX uniq_settings_user (user_id), PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE settings ADD CONSTRAINT FK_E545A0C5A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE settings DROP FOREIGN KEY FK_E545A0C5A76ED395');
        $this->addSql('DROP TABLE settings');
    }
}
