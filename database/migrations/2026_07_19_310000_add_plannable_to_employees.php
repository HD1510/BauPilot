<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Nicht jeder Mitarbeiter gehört in die Einteilung (z. B. Büro).
        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('plannable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('plannable');
        });
    }
};
