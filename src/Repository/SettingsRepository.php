<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Settings;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Settings>
 */
final class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    /**
     * Le paramétrage de l'utilisateur, ou les valeurs par défaut (non persistées)
     * s'il n'en a jamais enregistré — un utilisateur fraîchement créé doit pouvoir
     * calculer sans étape de configuration préalable.
     */
    public function forUser(User $user): Settings
    {
        return $this->findOneBy(['user' => $user]) ?? Settings::defaults($user);
    }
}
