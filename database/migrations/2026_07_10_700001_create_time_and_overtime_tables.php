<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M8 (Architekturblatt 4.2): Projektstunden mit client_uuid (offline-
// erfassbar) sowie Überstunden je Monat und deren Auszahlungen (1A-Rest
// aus M4).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('time_entries', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('work_date');
            $table->decimal('hours', 6, 2);
            $table->string('activity')->nullable();
            $table->uuid('client_uuid')->nullable();
            $table->unique(['company_id', 'client_uuid']);
            $table->index(['company_id', 'project_id', 'work_date']);
            $table->index(['company_id', 'employee_id', 'work_date']);
            $table->businessMeta();
        });

        Schema::create('overtime_entries', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('hours', 6, 2); // darf negativ sein (Abbau)
            $table->string('note')->nullable();
            $table->unique(['company_id', 'employee_id', 'year', 'month']);
            $table->businessMeta();
        });

        Schema::create('overtime_payouts', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('paid_on');
            $table->decimal('hours', 6, 2);
            $table->decimal('amount', 12, 2);
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_payouts');
        Schema::dropIfExists('overtime_entries');
        Schema::dropIfExists('time_entries');
    }
};
