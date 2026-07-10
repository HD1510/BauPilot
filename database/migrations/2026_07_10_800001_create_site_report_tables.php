<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M9 (Architekturblatt 4.2): Regieberichte mit fortlaufender Nummer je
// Firma, Unterschrift und Sperre (draft/signed) — offline erfassbar
// über client_uuid — samt Stunden-Zeilen je Bericht.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_reports', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('number');
            $table->date('report_date');
            $table->string('status')->default('draft'); // draft | signed
            $table->text('body_text')->nullable();
            $table->text('material_text')->nullable();
            $table->string('signature_path')->nullable();
            $table->timestampTz('signed_at')->nullable();
            $table->uuid('client_uuid')->nullable();
            $table->unique(['company_id', 'client_uuid']);
            $table->unique(['company_id', 'number']);
            $table->businessMeta();
        });

        Schema::create('site_report_entries', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('site_report_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->decimal('hours', 6, 2);
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_report_entries');
        Schema::dropIfExists('site_reports');
    }
};
