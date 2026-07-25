<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PunchEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PunchEvent>
 */
final class PunchEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PunchEvent::class);
    }

    /**
     * Charge les pointages déjà en base pour un ensemble de dates, afin que l'import
     * puisse dédupliquer et repérer les prévisionnels à supprimer.
     *
     * @param list<\DateTimeImmutable> $dates
     *
     * @return list<PunchEvent>
     */
    public function findByDates(array $dates): array
    {
        if ([] === $dates) {
            return [];
        }

        $days = array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates);

        return $this->createQueryBuilder('p')
            ->andWhere('p.date IN (:days)')
            ->setParameter('days', $days)
            ->getQuery()
            ->getResult();
    }
}
