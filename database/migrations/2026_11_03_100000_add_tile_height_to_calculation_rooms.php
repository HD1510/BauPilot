<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fliesenhöhe je Raum — leer heißt: Vorgabe der Kalkulation.
        Schema::table('calculation_rooms', function (Blueprint $table) {
            $table->decimal('tile_height', 5, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('calculation_rooms', function (Blueprint $table) {
            $table->dropColumn('tile_height');
        });
    }
};
