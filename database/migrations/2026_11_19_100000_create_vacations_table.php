<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Urlaube je Mitarbeiter — die Einteilung graut Betroffene aus
        // und zeigt sie je Tag automatisch an.
        Schema::create('vacations', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->businessMeta();

            $table->index(['company_id', 'ends_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacations');
    }
};
