<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Time\Minutes;
use App\Domain\Week\IsoWeek;
use App\Domain\Week\WeekFact;
use App\Domain\Work\WorkWeekAssembler;
use App\Entity\BalanceMovement;
use App\Entity\User;
use App\Repository\BalanceMovementRepository;
use App\Repository\DayEventRepository;
use App\Repository\EmployerReadingRepository;
use App\Repository\PunchEventRepository;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Les compteurs (spec §2) : RTT, et les trois destins d'une heure supplémentaire
 * (Récupération, Variable, Paiement). `Dispo`, `Transfert` et `Boni` restent hors
 * périmètre — leur alimentation n'est pas documentée (§8.3), confirmé par
 * Guillaume le 28/07/2026.
 *
 * Les montants crédités ne viennent jamais du client : ils sont recalculés
 * côté serveur ({@see WorkWeekAssembler}) pour la semaine concernée, jamais
 * acceptés tels quels d'un formulaire.
 */
final class BalanceController extends AbstractController
{
    public function __construct(
        private readonly PunchEventRepository $punches,
        private readonly EmployerReadingRepository $readings,
        private readonly DayEventRepository $events,
        private readonly SettingsRepository $settingsRepository,
        private readonly BalanceMovementRepository $balances,
        private readonly WorkWeekAssembler $assembler,
        private readonly EntityManagerInterface $entityManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/compteurs', name: 'balances', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        $balances = [];
        foreach (BalanceCounter::cases() as $counter) {
            $balances[] = ['counter' => $counter, 'amount' => $this->balances->balanceFor($user, $counter)];
        }

        return $this->render('balances/index.html.twig', ['balances' => $balances]);
    }

    /**
     * Crédite le RTT acquis d'une semaine (spec §4.3, plafonné à 2h). Remplace le
     * crédit existant plutôt que de le doubler si la semaine est recréditée après
     * un recalcul (édition de pointages passés, par exemple).
     */
    #[Route('/semaine/{year}/{week}/rtt', name: 'balance_credit_rtt', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['POST'])]
    public function creditRtt(int $year, int $week, #[CurrentUser] User $user): Response
    {
        $monday = IsoWeek::dates($year, $week)[0];
        $rttAcquired = $this->weekFact($user, $year, $week)->rttAcquired();

        $existing = $this->balances->findRttCreditForWeek($user, $monday);
        if (null !== $existing) {
            $this->entityManager->remove($existing);
            $this->entityManager->flush();
        }

        if ($rttAcquired->value() > 0) {
            $this->entityManager->persist(BalanceMovement::credit(
                $user,
                BalanceCounter::Rtt,
                $rttAcquired,
                $monday,
                $this->clock->now(),
                sprintf('RTT acquis semaine %d/%d', $week, $year),
            ));
            $this->entityManager->flush();
        }

        return $this->redirectToRoute('week', ['year' => $year, 'week' => $week]);
    }

    /**
     * Répartit les heures supplémentaires d'une semaine entre les trois destins
     * (spec §2). Ne peut pas dépasser ce qui reste réellement disponible : la part
     * déjà allouée (mouvements précédents sur cette semaine) est déduite du total
     * recalculé avant d'accepter la nouvelle allocation.
     */
    #[Route('/semaine/{year}/{week}/heures-sup', name: 'balance_allocate_overtime', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['POST'])]
    public function allocateOvertime(int $year, int $week, Request $request, #[CurrentUser] User $user): Response
    {
        $monday = IsoWeek::dates($year, $week)[0];
        $motif = '' !== trim((string) $request->request->get('motif', '')) ? trim((string) $request->request->get('motif')) : null;

        // Indexé par la valeur de l'enum (string) : un cas d'enum ne peut pas servir
        // de clé de tableau PHP.
        $requested = [
            BalanceCounter::Recuperation->value => (string) $request->request->get('recuperation', '00:00'),
            BalanceCounter::Variable->value => (string) $request->request->get('variable', '00:00'),
            BalanceCounter::Paiement->value => (string) $request->request->get('paiement', '00:00'),
        ];

        try {
            $amounts = array_map(static fn (string $value): Minutes => Minutes::fromClock($value), $requested);

            $totalRequested = array_reduce(
                $amounts,
                static fn (Minutes $carry, Minutes $amount): Minutes => $carry->plus($amount),
                Minutes::of(0),
            );

            $overtime = $this->weekFact($user, $year, $week)->overtimeMinutes();
            $alreadyAllocated = $this->allocatedOvertimeFor($user, $monday);
            $remaining = $overtime->minus($alreadyAllocated)->clampToZero();

            if ($totalRequested->value() > $remaining->value()) {
                throw new \InvalidArgumentException(sprintf(
                    'Tu demandes %s mais il ne reste que %s d\'heures supplémentaires non allouées cette semaine.',
                    $totalRequested->toClock(),
                    $remaining->toClock(),
                ));
            }

            foreach ($amounts as $counterValue => $amount) {
                if ($amount->value() > 0) {
                    $this->entityManager->persist(BalanceMovement::credit(
                        $user,
                        BalanceCounter::from($counterValue),
                        $amount,
                        $monday,
                        $this->clock->now(),
                        $motif,
                    ));
                }
            }
            $this->entityManager->flush();

            return $this->redirectToRoute('week', ['year' => $year, 'week' => $week]);
        } catch (\InvalidArgumentException $exception) {
            $this->addFlash('error', $exception->getMessage());

            return $this->redirectToRoute('week', ['year' => $year, 'week' => $week]);
        }
    }

    private function weekFact(User $user, int $year, int $week): WeekFact
    {
        $dates = IsoWeek::dates($year, $week);
        $settings = $this->settingsRepository->forUser($user);

        return $this->assembler->assemble(
            $dates,
            $this->punches->findByDates($user, $dates),
            $this->readings->latestMinutesByDates($user, $dates),
            $this->events->findByDates($user, $dates),
            $this->clock->now()->setTime(0, 0, 0),
            $settings,
        )->weekFact();
    }

    /**
     * La part des heures supplémentaires de cette semaine déjà allouée à un destin
     * (somme des crédits Récupération/Variable/Paiement datés du lundi de la semaine).
     */
    private function allocatedOvertimeFor(User $user, \DateTimeImmutable $monday): Minutes
    {
        $total = Minutes::of(0);
        foreach ($this->balances->findByUserAndDate($user, $monday) as $movement) {
            if (\in_array($movement->counter(), [BalanceCounter::Recuperation, BalanceCounter::Variable, BalanceCounter::Paiement], true)
                && $movement->isCredit()) {
                $total = $total->plus($movement->amount());
            }
        }

        return $total;
    }
}
