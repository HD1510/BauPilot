<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Wird geworfen, wenn ein mandantengebundenes Modell ohne aktiven
 * Firmenkontext abgefragt oder angelegt wird — z. B. in einem Queue-Job
 * oder Konsolenbefehl, der den Kontext nicht explizit gesetzt hat.
 * Bewusst eine Exception statt still leerer oder ungefilterter Ergebnisse.
 */
class MissingCompanyContext extends RuntimeException
{
    public static function make(): self
    {
        return new self(
            'Kein aktiver Firmenkontext. HTTP-Requests setzen ihn über die SetActiveCompany-Middleware; '
            .'Jobs und Konsolenbefehle müssen CompanyContext::runFor() verwenden.'
        );
    }
}
