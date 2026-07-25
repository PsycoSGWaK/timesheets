<?php

declare(strict_types=1);

namespace App\Domain\Adp;

use App\Entity\EmployerReading;
use App\Entity\PunchEvent;

/**
 * Ce qu'un import doit produire : les relevés à consigner, les pointages à créer et
 * les pointages prévisionnels à supprimer.
 *
 * Objet purement descriptif, calculé par {@see ImportPlanner} sans aucun effet de bord.
 * L'application effective (persist / remove) est laissée à la couche qui détient
 * l'EntityManager, ce qui rend toute la logique d'import testable sans base.
 */
final readonly class ImportPlan
{
    /**
     * @param list<EmployerReading> $readingsToRecord
     * @param list<PunchEvent>      $punchesToCreate
     * @param list<PunchEvent>      $provisionalToSupersede
     */
    public function __construct(
        private array $readingsToRecord,
        private array $punchesToCreate,
        private array $provisionalToSupersede,
    ) {
    }

    /**
     * @return list<EmployerReading>
     */
    public function readingsToRecord(): array
    {
        return $this->readingsToRecord;
    }

    /**
     * @return list<PunchEvent>
     */
    public function punchesToCreate(): array
    {
        return $this->punchesToCreate;
    }

    /**
     * @return list<PunchEvent>
     */
    public function provisionalToSupersede(): array
    {
        return $this->provisionalToSupersede;
    }
}
