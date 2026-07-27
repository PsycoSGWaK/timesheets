<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260727115603 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache punch_event, employer_reading et raw_import a un utilisateur (user_id NOT NULL).';
    }

    public function up(Schema $schema): void
    {
        // Rattacher un user_id NOT NULL a des lignes existantes exigerait une valeur
        // par defaut ; a ce stade du projet (une seule semaine importee en test), il
        // est plus simple de repartir de zero que de fabriquer une migration de
        // retro-remplissage pour des donnees jetables.
        $this->addSql('TRUNCATE TABLE punch_event');
        $this->addSql('TRUNCATE TABLE employer_reading');
        $this->addSql('TRUNCATE TABLE raw_import');

        // Ces trois tables ont ete creees sans moteur explicite (migration initiale)
        // et se sont retrouvees en MyISAM selon le defaut du serveur a l'epoque.
        // MyISAM ignore silencieusement les contraintes de cle etrangere : sans ce
        // passage a InnoDB, les FK ajoutees plus bas ne seraient jamais appliquees.
        $this->addSql('ALTER TABLE punch_event ENGINE=InnoDB');
        $this->addSql('ALTER TABLE employer_reading ENGINE=InnoDB');
        $this->addSql('ALTER TABLE raw_import ENGINE=InnoDB');

        $this->addSql('DROP INDEX idx_reading_date ON employer_reading');
        $this->addSql('ALTER TABLE employer_reading ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE employer_reading ADD CONSTRAINT FK_89F785F6A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('CREATE INDEX IDX_89F785F6A76ED395 ON employer_reading (user_id)');
        $this->addSql('CREATE INDEX idx_reading_user_date ON employer_reading (user_id, date)');
        $this->addSql('DROP INDEX uniq_punch_slot ON punch_event');
        $this->addSql('ALTER TABLE punch_event ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE punch_event ADD CONSTRAINT FK_F70D15B7A76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('CREATE INDEX IDX_F70D15B7A76ED395 ON punch_event (user_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_punch_slot ON punch_event (user_id, date, time, rang)');
        $this->addSql('ALTER TABLE raw_import ADD user_id INT NOT NULL');
        $this->addSql('ALTER TABLE raw_import ADD CONSTRAINT FK_5AAC65DA76ED395 FOREIGN KEY (user_id) REFERENCES app_user (id)');
        $this->addSql('CREATE INDEX IDX_5AAC65DA76ED395 ON raw_import (user_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE employer_reading DROP FOREIGN KEY FK_89F785F6A76ED395');
        $this->addSql('DROP INDEX IDX_89F785F6A76ED395 ON employer_reading');
        $this->addSql('DROP INDEX idx_reading_user_date ON employer_reading');
        $this->addSql('ALTER TABLE employer_reading DROP user_id');
        $this->addSql('CREATE INDEX idx_reading_date ON employer_reading (date)');
        $this->addSql('ALTER TABLE punch_event DROP FOREIGN KEY FK_F70D15B7A76ED395');
        $this->addSql('DROP INDEX IDX_F70D15B7A76ED395 ON punch_event');
        $this->addSql('DROP INDEX uniq_punch_slot ON punch_event');
        $this->addSql('ALTER TABLE punch_event DROP user_id');
        $this->addSql('CREATE UNIQUE INDEX uniq_punch_slot ON punch_event (date, time, rang)');
        $this->addSql('ALTER TABLE raw_import DROP FOREIGN KEY FK_5AAC65DA76ED395');
        $this->addSql('DROP INDEX IDX_5AAC65DA76ED395 ON raw_import');
        $this->addSql('ALTER TABLE raw_import DROP user_id');
    }
}
