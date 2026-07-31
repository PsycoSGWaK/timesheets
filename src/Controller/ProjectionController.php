<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Projection\LeaveTimeCalculator;
use App\Domain\Time\Minutes;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Le mode prévisionnel du quotidien : « à quelle heure puis-je partir ? » (spec §4.6).
 *
 * On saisit les trois horaires connus de la matinée (prise de poste, départ et retour
 * de pause) et l'outil rend l'heure de sortie visant l'objectif, pénalité de pause
 * comprise — la correction qu'aucun calcul mental ne fait spontanément.
 */
final class ProjectionController extends AbstractController
{
    #[Route('/quand-partir', name: 'projection', methods: ['GET'])]
    public function projection(
        Request $request,
        LeaveTimeCalculator $calculator,
        SettingsRepository $settingsRepository,
        #[CurrentUser] User $user,
    ): Response {
        $morningStart = (string) $request->query->get('morning_start', '');
        $lunchDeparture = (string) $request->query->get('lunch_departure', '');
        $lunchReturn = (string) $request->query->get('lunch_return', '');

        $estimate = null;
        $error = null;

        if ('' !== $morningStart || '' !== $lunchDeparture || '' !== $lunchReturn) {
            try {
                $estimate = $calculator->estimate(
                    Minutes::fromClock($morningStart),
                    Minutes::fromClock($lunchDeparture),
                    Minutes::fromClock($lunchReturn),
                    $settingsRepository->forUser($user),
                );
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('projection/index.html.twig', [
            'morningStart' => $morningStart,
            'lunchDeparture' => $lunchDeparture,
            'lunchReturn' => $lunchReturn,
            'estimate' => $estimate,
            'error' => $error,
        ]);
    }

    /**
     * Turbo Drive exige qu'une réponse à un POST redirige (pattern PRG) ;
     * le calcul lui-même reste porté par la route GET, rejouable et partageable par URL.
     */
    #[Route('/quand-partir', name: 'projection_calculate', methods: ['POST'])]
    public function calculate(Request $request): Response
    {
        return $this->redirectToRoute('projection', [
            'morning_start' => (string) $request->request->get('morning_start', ''),
            'lunch_departure' => (string) $request->request->get('lunch_departure', ''),
            'lunch_return' => (string) $request->request->get('lunch_return', ''),
        ]);
    }
}
