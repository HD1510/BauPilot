<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Import-Läufe (Architekturblatt 4.3/8): zweiphasig, Dry-Run vor Commit.
        Schema::create('import_runs', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('source_filename');
            $table->string('path', 500);
            $table->string('status', 20)->default('dry_run');
            $table->jsonb('stats');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        // Prüfbericht-Einträge: alles, was Bestätigung braucht.
        Schema::create('import_findings', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('import_run_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('message', 500);
            $table->jsonb('payload');
            $table->string('decision', 30)->nullable();
            $table->timestampsTz();
        });

        // Idempotenz: jeder importierte Datensatz trägt seine Herkunft
        // (Blatt, Zeile) — ein erneuter Lauf derselben Datei legt nichts
        // doppelt an (Architekturblatt Abschnitt 8).
        foreach (['customers', 'suppliers', 'outgoing_invoices', 'incoming_invoices', 'payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('source_ref')->nullable();
                $table->index(['company_id', 'source_ref']);
            });
        }
    }

    public function down(): void
    {
        foreach (['customers', 'suppliers', 'outgoing_invoices', 'incoming_invoices', 'payments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropIndex(['company_id', 'source_ref']);
                $table->dropColumn('source_ref');
            });
        }

        Schema::dropIfExists('import_findings');
        Schema::dropIfExists('import_runs');
    }
};
