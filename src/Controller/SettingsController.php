<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Time\Minutes;
use App\Entity\Settings;
use App\Entity\User;
use App\Repository\SettingsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * L'écran de paramétrage : les seuils que les calculateurs appliquaient jusqu'ici
 * en dur (pause minimale, fenêtre autorisée, journées de référence, plafond RTT),
 * modifiables par l'utilisateur (spec §3).
 */
final class SettingsController extends AbstractController
{
    /** Champ du formulaire => accesseur Minutes sur Settings. */
    private const FIELDS = [
        'pause_minimale' => 'pauseMinimale',
        'fenetre_debut' => 'fenetreDebut',
        'fenetre_fin' => 'fenetreFin',
        'journee_reference_contractuelle' => 'journeeReferenceContractuelle',
        'journee_reference_effective' => 'journeeReferenceEffective',
        'rtt_max' => 'rttMax',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SettingsRepository $settingsRepository,
    ) {
    }

    #[Route('/parametres', name: 'settings', methods: ['GET'])]
    public function show(#[CurrentUser] User $user): Response
    {
        return $this->render('settings/index.html.twig', [
            'values' => $this->valuesOf($this->settingsRepository->forUser($user)),
            'error' => null,
        ]);
    }

    #[Route('/parametres', name: 'settings_save', methods: ['POST'])]
    public function save(Request $request, #[CurrentUser] User $user): Response
    {
        $submitted = [];
        foreach (array_keys(self::FIELDS) as $field) {
            $submitted[$field] = (string) $request->request->get($field, '');
        }

        try {
            $minutes = array_map(
                static fn (string $value): Minutes => Minutes::fromClock($value),
                $submitted,
            );

            $settings = $this->settingsRepository->forUser($user);
            $isNew = null === $settings->id();

            $settings->update(
                pauseMinimale: $minutes['pause_minimale']->value(),
                fenetreDebut: $minutes['fenetre_debut']->value(),
                fenetreFin: $minutes['fenetre_fin']->value(),
                journeeReferenceContractuelle: $minutes['journee_reference_contractuelle']->value(),
                journeeReferenceEffective: $minutes['journee_reference_effective']->value(),
                rttMax: $minutes['rtt_max']->value(),
            );

            if ($isNew) {
                $this->entityManager->persist($settings);
            }
            $this->entityManager->flush();

            $this->addFlash('success', 'Paramétrage enregistré.');

            return $this->redirectToRoute('settings');
        } catch (\InvalidArgumentException $exception) {
            return $this->render('settings/index.html.twig', [
                'values' => $submitted,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function valuesOf(Settings $settings): array
    {
        $values = [];
        foreach (self::FIELDS as $field => $accessor) {
            /** @var Minutes $minutes */
            $minutes = $settings->{$accessor}();
            $values[$field] = $minutes->toClock();
        }

        return $values;
    }
}
