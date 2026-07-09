<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Admin = 'admin';
    case Office = 'office';
    case Site = 'site';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Office => 'Büro',
            self::Site => 'Baustelle',
        };
    }

    /**
     * Finanzdaten (Rechnungen, offene Posten, Beträge) sieht die Rolle
     * Baustelle nie — die Werte verlassen den Server gar nicht erst.
     */
    public function canViewFinancials(): bool
    {
        return $this !== self::Site;
    }
}
