<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Balance\BalanceCounter;
use App\Domain\Projection\WeekProjectionCalculator;
use App\Domain\Week\IsoWeek;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\BalanceMovementRepository;
use App\Repository\SettingsRepository;
use App\Week\DayEditPanel;
use App\Week\DayEditPanelLoader;
use App\Week\EventQuotaLoader;
use App\Week\WeekLoader;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Affiche une semaine : notre recalcul face au relevé d'ADP, jour par jour, plus
 * l'agrégat hebdomadaire (total, RTT, heures supplémentaires) et les écarts.
 */
final class WeekController extends AbstractController
{
    public function __construct(
        private readonly SettingsRepository $settingsRepository,
        private readonly WeekLoader $weekLoader,
        private readonly DayEditPanelLoader $dayEditPanelLoader,
        private readonly BalanceMovementRepository $balances,
        private readonly EventQuotaLoader $eventQuotaLoader,
        private readonly WeekProjectionCalculator $projectionCalculator,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/semaine', name: 'week_current', methods: ['GET'])]
    public function currentWeek(#[CurrentUser] User $user, Request $request): Response
    {
        $today = $this->today();

        return $this->renderWeek($user, (int) $today->format('o'), (int) $today->format('W'), $request);
    }

    #[Route('/semaine/{year}/{week}', name: 'week', requirements: ['year' => '\d{4}', 'week' => '\d{1,2}'], methods: ['GET'])]
    public function week(int $year, int $week, #[CurrentUser] User $user, Request $request): Response
    {
        return $this->renderWeek($user, $year, $week, $request);
    }

    /**
     * Cible du sélecteur natif <input type="week"> (spec §2, sélection de semaine
     * plus rapide qu'un défilement précédent/suivant). Une valeur illisible ramène
     * simplement à la semaine courante plutôt que d'échouer.
     */
    #[Route('/semaine/aller', name: 'week_goto', methods: ['GET'])]
    public function goto(Request $request): Response
    {
        $value = (string) $request->query->get('semaine', '');

        if (1 === preg_match('/^(\d{4})-W(\d{2})$/', $value, $matches)) {
            return $this->redirectToRoute('week', ['year' => (int) $matches[1], 'week' => (int) $matches[2]]);
        }

        return $this->redirectToRoute('week_current');
    }

    private function renderWeek(User $user, int $year, int $week, Request $request): Response
    {
        $dates = IsoWeek::dates($year, $week);
        $monday = $dates[0];

        $today = $this->today();
        $settings = $this->settingsRepository->forUser($user);

        $workWeek = $this->weekLoader->load($user, $dates, $today, $settings);
        $dayPanel = $this->loadDayPanel($request, $user, $settings);

        $remainingWorkingDays = $this->countRemainingWorkingDays($dates, $today, $settings);
        $workedMinutes = $workWeek->weekFact()->workedMinutes();

        $projectionReference = $this->projectionCalculator->project(
            $workedMinutes,
            $remainingWorkingDays,
            $settings,
            $settings->weeklyReference(),
        );
        $projection = $this->projectionCalculator->project(
            $workedMinutes,
            $remainingWorkingDays,
            $settings,
        );

        $previous = $monday->modify('-7 days');
        $next = $monday->modify('+7 days');

        return $this->render('week/index.html.twig', [
            'workWeek' => $workWeek,
            'dayPanel' => $dayPanel,
            'balances' => $this->balancesOverview($user),
            'eventQuotas' => $this->eventQuotaLoader->load($user, $settings, (int) $today->format('Y')),
            'quotaYear' => (int) $today->format('Y'),
            'projectionReference' => $projectionReference,
            'projection' => $projection,
            'week' => $week,
            'monday' => $monday,
            'sunday' => $monday->modify('+6 days'),
            'previous' => ['year' => (int) $previous->format('o'), 'week' => (int) $previous->format('W')],
            'next' => ['year' => (int) $next->format('o'), 'week' => (int) $next->format('W')],
            'pickerValue' => sprintf('%04d-W%02d', $year, $week),
        ]);
    }

    /**
     * Le solde de chaque compteur (spec §2), affiché à droite de « Ma semaine »
     * plutôt que sur un écran séparé (règle du 29/07/2026) — même donnée que
     * {@see BalanceController::index()}.
     *
     * @return list<array{counter: BalanceCounter, amount: \App\Domain\Time\Minutes}>
     */
    private function balancesOverview(User $user): array
    {
        $balances = [];
        foreach (BalanceCounter::cases() as $counter) {
            $balances[] = ['counter' => $counter, 'amount' => $this->balances->balanceFor($user, $counter)];
        }

        return $balances;
    }

    /**
     * Panneau d'édition d'une journée, intégré à l'écran de la semaine plutôt que sur
     * une page séparée (règle du 29/07/2026) : absent tant qu'aucun jour n'est
     * sélectionné via le paramètre `jour`, une valeur illisible étant traitée comme
     * une absence de sélection plutôt qu'une erreur.
     */
    private function loadDayPanel(Request $request, User $user, Settings $settings): ?DayEditPanel
    {
        $jour = (string) $request->query->get('jour', '');
        if (1 !== preg_match('/^\d{4}-\d{2}-\d{2}$/', $jour)) {
            return null;
        }

        return $this->dayEditPanelLoader->load($user, new \DateTimeImmutable($jour), $settings);
    }

    /**
     * « Aujourd'hui » à minuit, selon l'horloge injectée — jamais l'horloge système
     * directement, pour que la consolidation ADP (§4bis) reste testable de façon
     * déterministe.
     */
    private function today(): \DateTimeImmutable
    {
        return $this->clock->now()->setTime(0, 0, 0);
    }

    /**
     * Jours ouvrés (hors jours de repos déclarés, spec du 28/07/2026) de la semaine
     * encore à venir, aujourd'hui inclus : ceux sur lesquels la répartition du temps
     * restant a du sens.
     *
     * @param list<\DateTimeImmutable> $dates
     */
    private function countRemainingWorkingDays(array $dates, \DateTimeImmutable $today, Settings $settings): int
    {
        $count = 0;
        foreach ($dates as $date) {
            if (!$settings->estJourDeRepos($date) && $date >= $today) {
                ++$count;
            }
        }

        return $count;
    }
}

