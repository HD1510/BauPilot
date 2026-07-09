<?php

namespace App\Support\Deadlines;

use Carbon\CarbonImmutable;

/**
 * Eine berechnete Frist (Architekturblatt Abschnitt 5): nie gespeichert,
 * „erledigen" heißt immer, die Ursache zu bearbeiten.
 */
final readonly class Deadline
{
    public function __construct(
        public DeadlineKind $kind,
        public CarbonImmutable $dueOn,
        public string $title,
        public ?string $subtitle,
        public string $url,
        public bool $financial,
    ) {}

    public function isOverdue(CarbonImmutable $today): bool
    {
        return $this->dueOn->lessThan($today);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(CarbonImmutable $today): array
    {
        return [
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'due_on' => $this->dueOn->toDateString(),
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'url' => $this->url,
            'overdue' => $this->isOverdue($today),
        ];
    }
}
