<?php

declare(strict_types=1);

namespace App\Controller;

use App\Domain\Adp\AdpParser;
use App\Import\AdpImporter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * L'écran central : coller le texte ADP, voir ce qui a été compris, valider l'import.
 *
 * Le flux est en deux temps sans état serveur : « Aperçu » analyse et affiche le
 * résultat sans rien écrire ; « Confirmer » réanalyse et importe. Le texte voyage
 * dans un champ caché entre les deux, l'analyse étant sans coût.
 */
final class ImportController extends AbstractController
{
    #[Route('/', name: 'home', methods: ['GET'])]
    public function home(): Response
    {
        return $this->redirectToRoute('import');
    }

    #[Route('/import', name: 'import', methods: ['GET', 'POST'])]
    public function import(Request $request, AdpParser $parser, AdpImporter $importer): Response
    {
        $payload = trim((string) $request->request->get('payload', ''));
        $year = (int) $request->request->get('year', (int) date('Y'));
        $action = (string) $request->request->get('action', '');

        $week = null;
        $result = null;
        $error = null;

        if ($request->isMethod('POST') && '' !== $payload) {
            try {
                if ('importer' === $action) {
                    $plan = $importer->import($payload, $year);
                    $result = [
                        'punchesCreated' => \count($plan->punchesToCreate()),
                        'readingsRecorded' => \count($plan->readingsToRecord()),
                        'provisionalSuperseded' => \count($plan->provisionalToSupersede()),
                    ];
                }

                $week = $parser->parse($payload, $year);
            } catch (\InvalidArgumentException $exception) {
                $error = $exception->getMessage();
            }
        }

        return $this->render('import/index.html.twig', [
            'payload' => $payload,
            'year' => $year,
            'week' => $week,
            'result' => $result,
            'error' => $error,
        ]);
    }
}
