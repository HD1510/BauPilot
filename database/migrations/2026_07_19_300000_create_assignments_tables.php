<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Einteilung: Wer ist an welchem Tag auf welcher Baustelle, mit
        // welchem Fahrzeug. Baustelle ist ein Projekt oder Freitext.
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->date('work_date');
            $table->foreignId('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('site')->nullable();
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'work_date']);
        });

        Schema::create('assignment_employee', function (Blueprint $table) {
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();

            $table->primary(['assignment_id', 'employee_id']);
        });

        Schema::create('assignment_vehicle', function (Blueprint $table) {
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();

            $table->primary(['assignment_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_vehicle');
        Schema::dropIfExists('assignment_employee');
        Schema::dropIfExists('assignments');
    }
};
