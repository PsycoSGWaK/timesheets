<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Time\Minutes;
use App\Entity\EmployerReading;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmployerReading>
 */
final class EmployerReadingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmployerReading::class);
    }

    /**
     * Le dernier total relevé chez ADP pour chacune des dates. Les relevés étant
     * append-only, on retient la plus récente observation par date (§4bis).
     *
     * @param list<\DateTimeImmutable> $dates
     *
     * @return array<string, Minutes> indexé par date « Y-m-d »
     */
    public function latestMinutesByDates(User $user, array $dates): array
    {
        if ([] === $dates) {
            return [];
        }

        $days = array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates);

        $readings = $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.date IN (:days)')
            ->setParameter('user', $user)
            ->setParameter('days', $days)
            ->orderBy('r.observedAt', 'ASC')
            ->getQuery()
            ->getResult();

        $latest = [];
        foreach ($readings as $reading) {
            // Parcours par observedAt croissant : la dernière observation écrase les précédentes.
            $latest[$reading->date()->format('Y-m-d')] = $reading->employerMinutes();
        }

        return $latest;
    }

    /**
     * Tous les relevés d'une date (l'historique append-only peut en contenir
     * plusieurs) — pour les supprimer d'un coup quand un import s'est trompé.
     *
     * @return list<EmployerReading>
     */
    public function findByDate(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->setTime(0, 0, 0, 0))
            ->getQuery()
            ->getResult();
    }
}
