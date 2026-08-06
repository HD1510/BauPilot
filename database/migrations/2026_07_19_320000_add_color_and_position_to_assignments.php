<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Farbe je Eintrag (Zeitraum-Anlagen teilen sich eine Farbe)
        // und Sortierreihenfolge innerhalb eines Tages.
        Schema::table('assignments', function (Blueprint $table) {
            $table->unsignedSmallInteger('color')->default(0);
            $table->unsignedSmallInteger('position')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('assignments', function (Blueprint $table) {
            $table->dropColumn(['color', 'position']);
        });
    }
};
