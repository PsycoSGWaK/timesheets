<?php

declare(strict_types=1);

namespace App\Import;

use App\Domain\Adp\AdpParser;
use App\Domain\Adp\ImportPlan;
use App\Domain\Adp\ImportPlanner;
use App\Domain\Adp\ParsedWeek;
use App\Entity\PunchEvent;
use App\Entity\RawImport;
use App\Entity\User;
use App\Repository\PunchEventRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Importe une semaine ADP collée : archive le texte, analyse la grammaire, calcule le
 * plan d'import et l'applique en base.
 *
 * Toute la décision vit dans {@see ImportPlanner} (pur, sans base) ; ce service se
 * contente de la traduire en opérations Doctrine (persist / remove) au sein d'une
 * seule transaction (un flush). L'import est idempotent sur les pointages grâce à la
 * contrainte d'unicité (date, heure, rang) ; les relevés ADP, eux, s'ajoutent à
 * chaque passage.
 */
final class AdpImporter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AdpParser $parser,
        private readonly ImportPlanner $planner,
        private readonly PunchEventRepository $punches,
    ) {
    }

    public function import(User $user, string $clipboard, int $year, ?\DateTimeImmutable $importedAt = null): ImportPlan
    {
        $importedAt ??= new \DateTimeImmutable();

        $week = $this->parser->parse($clipboard, $year);

        $this->entityManager->persist(RawImport::capture($user, $clipboard, $year, $importedAt));

        $plan = $this->planner->plan($user, $week, $importedAt, $this->existingPunchesByDate($user, $week));

        foreach ($plan->provisionalToSupersede() as $punch) {
            $this->entityManager->remove($punch);
        }
        foreach ($plan->punchesToCreate() as $punch) {
            $this->entityManager->persist($punch);
        }
        foreach ($plan->readingsToRecord() as $reading) {
            $this->entityManager->persist($reading);
        }

        $this->entityManager->flush();

        return $plan;
    }

    /**
     * @return array<string, list<PunchEvent>>
     */
    private function existingPunchesByDate(User $user, ParsedWeek $week): array
    {
        $dates = array_map(static fn ($day): \DateTimeImmutable => $day->date(), $week->days());

        $byDate = [];
        foreach ($this->punches->findByDates($user, $dates) as $punch) {
            $byDate[$punch->date()->format('Y-m-d')][] = $punch;
        }

        return $byDate;
    }
}
