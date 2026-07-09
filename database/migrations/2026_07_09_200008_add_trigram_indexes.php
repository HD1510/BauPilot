<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

return new class extends Migration
{
    /**
     * Trigramm-Indizes für die Dubletten-Erkennung (Architekturblatt
     * Abschnitt 5). Nur auf PostgreSQL; auf anderen Treibern (z. B. sqlite
     * in Tests) rechnet der DuplicateFinder die Ähnlichkeit in PHP.
     */
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        try {
            DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        } catch (Throwable $e) {
            // Ohne Superuser-Recht bleibt nur der PHP-Fallback; die
            // Anwendung funktioniert trotzdem.
            Log::warning('pg_trgm konnte nicht aktiviert werden: '.$e->getMessage());

            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS customers_normalized_name_trgm ON customers USING gin (normalized_name gin_trgm_ops)');
        DB::statement('CREATE INDEX IF NOT EXISTS suppliers_normalized_name_trgm ON suppliers USING gin (normalized_name gin_trgm_ops)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS customers_normalized_name_trgm');
        DB::statement('DROP INDEX IF EXISTS suppliers_normalized_name_trgm');
    }
};
