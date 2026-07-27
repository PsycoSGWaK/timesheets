<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Day\DayEventCode;
use App\Domain\Day\DayHalf;
use App\Domain\Day\DayPortion;
use App\Domain\Time\Minutes;
use App\Entity\BalanceMovement;
use App\Entity\DayEvent;
use App\Entity\User;
use App\Repository\BalanceMovementRepository;
use App\Repository\DayEventRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Déclare ou retire l'événement d'un jour (CP, CA, RTT, JF, TT — spec §2), depuis
 * l'écran « Ma semaine ». Un seul événement par jour : en déclarer un nouveau
 * remplace le précédent plutôt que d'échouer sur la contrainte d'unicité.
 *
 * Poser un jour RTT débite le compteur RTT (spec §4.3 : « se pose en jours »). Retirer
 * ou remplacer un jour RTT annule ce débit par un mouvement compensateur — jamais de
 * suppression, le ledger reste append-only ({@see BalanceMovement}).
 */
final class DayEventController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DayEventRepository $events,
        private readonly BalanceMovementRepository $balances,
        private readonly SettingsRepository $settingsRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/semaine/evenement', name: 'day_event_declare', methods: ['POST'])]
    public function declare(Request $request, #[CurrentUser] User $user): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));
        $code = DayEventCode::from((string) $request->request->get('code'));
        $portion = DayPortion::from((string) $request->request->get('portion', DayPortion::Full->value));
        // Matin/après-midi n'a de sens que pour un TT en demi-journée (règle
        // précise du 28/07/2026) ; sans quoi la valorisation retombe sur la simple
        // moitié de la référence, comme pour les autres codes.
        $half = DayEventCode::Teletravail === $code && DayPortion::Half === $portion
            ? DayHalf::from((string) $request->request->get('half', DayHalf::Matin->value))
            : null;

        $existing = $this->events->findOneByDate($user, $date);
        if (null !== $existing) {
            $this->reverseRttDebit($user, $existing, $date);

            // Flush separe : au sein d'un meme flush, Doctrine insere avant de
            // supprimer, ce qui violerait la contrainte d'unicite (user, date)
            // tant que l'ancien evenement n'est pas encore efface.
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        $this->entityManager->persist(DayEvent::declare($user, $date, $code, $portion, $half));

        if (DayEventCode::Rtt === $code) {
            $settings = $this->settingsRepository->forUser($user);
            $this->entityManager->persist(BalanceMovement::debit(
                $user,
                BalanceCounter::Rtt,
                $portion->of($settings->journeeReferenceContractuelle()),
                $date,
                $this->clock->now(),
                'Jour RTT posé',
            ));
        }

        $this->entityManager->flush();

        return $this->redirectToWeekOf($date);
    }

    #[Route('/semaine/evenement/supprimer', name: 'day_event_remove', methods: ['POST'])]
    public function remove(Request $request, #[CurrentUser] User $user): Response
    {
        $date = new \DateTimeImmutable((string) $request->request->get('date'));

        $existing = $this->events->findOneByDate($user, $date);
        if (null !== $existing) {
            $this->reverseRttDebit($user, $existing, $date);
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        return $this->redirectToWeekOf($date);
    }

    /**
     * Annule le débit RTT d'un événement retiré ou remplacé, s'il en portait un —
     * par un crédit compensateur du même montant, jamais par une suppression.
     */
    private function reverseRttDebit(User $user, DayEvent $replaced, \DateTimeImmutable $date): void
    {
        if (DayEventCode::Rtt !== $replaced->code()) {
            return;
        }

        foreach ($this->balances->findByUserAndDate($user, $date) as $movement) {
            if (BalanceCounter::Rtt === $movement->counter() && !$movement->isCredit()) {
                $this->entityManager->persist(BalanceMovement::credit(
                    $user,
                    BalanceCounter::Rtt,
                    Minutes::of(abs($movement->amount()->value())),
                    $date,
                    $this->clock->now(),
                    'Annulation : jour RTT retiré ou remplacé',
                ));
            }
        }
    }

    private function redirectToWeekOf(\DateTimeImmutable $date): Response
    {
        return $this->redirectToRoute('week', [
            'year' => (int) $date->format('o'),
            'week' => (int) $date->format('W'),
        ]);
    }
}
