<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\DayEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DayEvent>
 */
final class DayEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DayEvent::class);
    }

    /**
     * @param list<\DateTimeImmutable> $dates
     *
     * @return array<string, DayEvent> indexé par date « Y-m-d »
     */
    public function findByDates(User $user, array $dates): array
    {
        if ([] === $dates) {
            return [];
        }

        $days = array_map(static fn (\DateTimeImmutable $d): string => $d->format('Y-m-d'), $dates);

        $events = $this->createQueryBuilder('e')
            ->andWhere('e.user = :user')
            ->andWhere('e.date IN (:days)')
            ->setParameter('user', $user)
            ->setParameter('days', $days)
            ->getQuery()
            ->getResult();

        $byDate = [];
        foreach ($events as $event) {
            $byDate[$event->date()->format('Y-m-d')] = $event;
        }

        return $byDate;
    }

    public function findOneByDate(User $user, \DateTimeImmutable $date): ?DayEvent
    {
        return $this->findOneBy(['user' => $user, 'date' => $date->setTime(0, 0, 0, 0)]);
    }
}
