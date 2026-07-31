<?php

declare(strict_types=1);

namespace App\Balance;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Time\Minutes;
use App\Entity\BalanceMovement;
use App\Entity\User;
use App\Repository\BalanceMovementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Tient le crédit RTT d'une semaine à jour du recalcul courant, sans action de
 * l'utilisateur : appelé à chaque affichage de la semaine ({@see \App\Controller\WeekController})
 * plutôt que déclenché par un bouton, pour que le compteur ne dépende jamais d'un
 * clic oublié.
 *
 * Remplace le crédit existant plutôt que de le doubler si la semaine est
 * recréditée après un recalcul (édition de pointages passés, par exemple) ;
 * n'écrit rien si le montant n'a pas changé depuis le dernier passage.
 */
final class RttWeekCreditor
{
    public function __construct(
        private readonly BalanceMovementRepository $balances,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    public function sync(User $user, \DateTimeImmutable $monday, int $isoYear, int $isoWeek, Minutes $rttAcquired): void
    {
        $existing = $this->balances->findRttCreditForWeek($user, $monday);

        if (null !== $existing && $existing->amount()->value() === $rttAcquired->value()) {
            return;
        }

        if (null === $existing && 0 === $rttAcquired->value()) {
            return;
        }

        if (null !== $existing) {
            $this->entityManager->remove($existing);
        }

        if ($rttAcquired->value() > 0) {
            $this->entityManager->persist(BalanceMovement::credit(
                $user,
                BalanceCounter::Rtt,
                $rttAcquired,
                $monday,
                $this->clock->now(),
                sprintf('RTT acquis semaine %d/%d', $isoWeek, $isoYear),
            ));
        }

        $this->entityManager->flush();
    }
}
