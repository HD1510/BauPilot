<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Freitext zum Urlaub (z. B. „nur vormittags", „Kur").
        Schema::table('vacations', function (Blueprint $table) {
            $table->string('notes', 500)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('vacations', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
