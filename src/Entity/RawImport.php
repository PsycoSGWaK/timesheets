<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Le texte ADP collé, conservé intégralement.
 *
 * Garder le presse-papier tel quel permet de rejouer le parseur sur l'historique sans
 * ressaisie (source-adp §5.2) : quand les règles d'analyse évoluent, on retraite les
 * imports passés au lieu de redemander les données. L'année, absente du texte source,
 * est mémorisée avec lui pour que le rejeu reste fidèle.
 */
#[ORM\Entity]
#[ORM\Table(name: 'raw_import')]
final class RawImport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $rawPayload;

    #[ORM\Column(type: 'smallint')]
    private int $year;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $importedAt;

    private function __construct(string $rawPayload, int $year, \DateTimeImmutable $importedAt)
    {
        if ('' === trim($rawPayload)) {
            throw new \InvalidArgumentException('Un import sans texte à conserver n\'a pas de sens.');
        }

        $this->rawPayload = $rawPayload;
        $this->year = $year;
        $this->importedAt = $importedAt;
    }

    public static function capture(string $rawPayload, int $year, \DateTimeImmutable $importedAt): self
    {
        return new self($rawPayload, $year, $importedAt);
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function rawPayload(): string
    {
        return $this->rawPayload;
    }

    public function year(): int
    {
        return $this->year;
    }

    public function importedAt(): \DateTimeImmutable
    {
        return $this->importedAt;
    }
}
