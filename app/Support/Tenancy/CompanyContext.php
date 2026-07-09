<?php

namespace App\Support\Tenancy;

use App\Exceptions\MissingCompanyContext;
use App\Models\Company;

/**
 * Hält die aktive Firma des laufenden Requests bzw. Jobs. Im HTTP-Kontext
 * setzt die SetActiveCompany-Middleware den Wert aus der Session; Jobs und
 * Konsolenbefehle setzen ihn explizit über runFor() — nie implizit.
 */
class CompanyContext
{
    private ?Company $company = null;

    public function set(Company $company): void
    {
        $this->company = $company;
    }

    public function clear(): void
    {
        $this->company = null;
    }

    public function current(): ?Company
    {
        return $this->company;
    }

    public function currentId(): ?int
    {
        return $this->company?->id;
    }

    public function requireCompany(): Company
    {
        return $this->company ?? throw MissingCompanyContext::make();
    }

    public function requireId(): int
    {
        return $this->requireCompany()->id;
    }

    /**
     * Führt eine Aktion im Kontext einer bestimmten Firma aus und stellt
     * den vorherigen Kontext danach wieder her. Der vorgesehene Weg für
     * Jobs, die je Firma arbeiten, und für den Scheduler.
     *
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    public function runFor(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->company = $company;

        try {
            return $callback();
        } finally {
            $this->company = $previous;
        }
    }
}
