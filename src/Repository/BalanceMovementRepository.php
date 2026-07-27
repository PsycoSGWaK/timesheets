<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Time\Minutes;
use App\Entity\BalanceMovement;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BalanceMovement>
 */
final class BalanceMovementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BalanceMovement::class);
    }

    /**
     * Le solde d'un compteur : la somme de tous ses mouvements (crédits positifs,
     * débits négatifs). Jamais stocké directement — toujours recalculé depuis
     * l'historique, comme {@see \App\Entity\EmployerReading} pour les mêmes raisons
     * (une valeur unique mutable ne raconte pas comment on y est arrivé).
     */
    public function balanceFor(User $user, BalanceCounter $counter): Minutes
    {
        $sum = $this->createQueryBuilder('m')
            ->select('COALESCE(SUM(m.amount), 0) AS total')
            ->andWhere('m.user = :user')
            ->andWhere('m.counter = :counter')
            ->setParameter('user', $user)
            ->setParameter('counter', $counter)
            ->getQuery()
            ->getSingleScalarResult();

        return Minutes::of((int) $sum);
    }

    /**
     * Le crédit RTT déjà enregistré pour une semaine donnée (identifiée par le lundi),
     * s'il existe — pour permettre de le remplacer plutôt que de le dupliquer si la
     * semaine est recréditée après un recalcul.
     */
    public function findRttCreditForWeek(User $user, \DateTimeImmutable $monday): ?BalanceMovement
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.counter = :counter')
            ->andWhere('m.date = :date')
            ->andWhere('m.amount > 0')
            ->setParameter('user', $user)
            ->setParameter('counter', BalanceCounter::Rtt)
            ->setParameter('date', $monday->setTime(0, 0, 0, 0))
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<BalanceMovement>
     */
    public function findByUserAndDate(User $user, \DateTimeImmutable $date): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :user')
            ->andWhere('m.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date->setTime(0, 0, 0, 0))
            ->getQuery()
            ->getResult();
    }
}
