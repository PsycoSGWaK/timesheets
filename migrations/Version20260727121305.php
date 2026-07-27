<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727121305 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la table day_event (evenements du jour : CP, CA, RTT, JF, TT).';
    }

    public function up(Schema $schema): void
    {
        // ENGINE explicite : une precedente migration s'est retrouvee en MyISAM par
        // defaut, ce qui ignore silencieusement les cles etrangeres (cf. Version20260727115603).
        $this->addSql('CREATE TABLE day_event (id INT AUTO_INCREMENT NOT NULL, date DATE NOT NULL, code VARCHAR(255) NOT NULL, quotite VARCHAR(255) NOT NULL, user_id INT NOT NULL, INDEX IDX_8263B643A76ED395 (user_id), UNIQUE INDEX uniq_day_event_slot (user_id, date), PRIMARY KEY (id)) ENGINE=InnoDB DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE day_event ADD CONSTRAINT FK_8263B643A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE day_event DROP FOREIGN KEY FK_8263B643A76ED395');
        $this->addSql('DROP TABLE day_event');
    }
}
