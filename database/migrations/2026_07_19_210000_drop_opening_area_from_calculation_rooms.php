<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fenster-/Öffnungsflächen werden nicht mehr abgezogen: Das
        // Ausarbeiten ist mehr Arbeit als die Fläche selbst — sie
        // zählen voll mit, damit der Preis stimmt.
        Schema::table('calculation_rooms', function (Blueprint $table) {
            $table->dropColumn('opening_area');
        });
    }

    public function down(): void
    {
        Schema::table('calculation_rooms', function (Blueprint $table) {
            $table->decimal('opening_area', 8, 2)->default(0);
        });
    }
};
