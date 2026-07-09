<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('retentions', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            // Polymorph (v1.1): Haft-/Deckungsrücklass auf Ausgangs- wie
            // Eingangsrechnungen (Subunternehmer).
            $table->morphs('retainable');
            $table->string('kind', 20);
            $table->decimal('percent', 5, 2)->nullable();
            $table->decimal('amount', 12, 2);
            $table->date('due_on');
            $table->string('note')->nullable();
            $table->timestampTz('received_at')->nullable();
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retentions');
    }
};
