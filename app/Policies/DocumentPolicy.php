<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\Document;
use App\Models\User;

/**
 * Datei-Anhänge: Sichtbarkeit richtet sich nach dem Elternobjekt —
 * Beleg-Anhänge sind Finanzdaten, Projektdateien (Pläne, Fotos) sieht
 * auch die Baustelle.
 */
class DocumentPolicy
{
    private const FINANCIAL_TYPES = ['offer', 'outgoing_invoice', 'incoming_invoice', 'external_offer', 'change_order'];

    public function view(User $user, Document $document): bool
    {
        $role = $user->currentRole();

        if ($role === null) {
            return false;
        }

        if (in_array($document->documentable_type, self::FINANCIAL_TYPES, true)) {
            return $role->canViewFinancials();
        }

        return true;
    }

    public function delete(User $user, Document $document): bool
    {
        if (! $this->view($user, $document)) {
            return false;
        }

        // Eigene Uploads (z. B. ein verwackeltes Baustellenfoto) darf
        // auch die Baustelle wieder entfernen (M7).
        if ($document->created_by === $user->id) {
            return true;
        }

        return in_array($user->currentRole(), [CompanyRole::Admin, CompanyRole::Office], true);
    }
}
