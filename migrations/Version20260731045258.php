<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Retire le destin "Variable" des heures supplémentaires (une heure sup est
 * payée ou récupérée, jamais autre chose — décision du 31/07/2026). Purge les
 * mouvements existants sur ce compteur avant de retirer le cas de l'enum
 * BalanceCounter, pour ne jamais tenter d'hydrater une valeur qui n'existe
 * plus côté PHP.
 */
final class Version20260731045258 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Purge les mouvements du compteur "variable", retire du destin des heures supplémentaires';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("DELETE FROM balance_movement WHERE counter = 'variable'");
    }

    public function down(Schema $schema): void
    {
        // Mouvements purgés irrécupérables : rien à rejouer dans l'autre sens.
    }
}
