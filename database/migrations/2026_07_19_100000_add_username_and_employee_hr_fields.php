<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Anmeldung per Benutzername: E-Mail wird optional (Baustellen-
        // Konten haben oft keine), der Benutzername ist eindeutig.
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 50)->nullable()->unique();
            $table->string('email')->nullable()->change();
        });

        // Personalakte (Architekturblatt 4.3): Adresse, Geburtsdatum,
        // Ein-/Austritt, SV-Nummer und IBAN direkt am Mitarbeiter.
        Schema::table('employees', function (Blueprint $table) {
            $table->string('address', 500)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('started_on')->nullable();
            $table->date('ended_on')->nullable();
            $table->string('social_security_number', 20)->nullable();
            $table->string('iban', 34)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn([
                'address',
                'birth_date',
                'started_on',
                'ended_on',
                'social_security_number',
                'iban',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['username']);
            $table->dropColumn('username');
            $table->string('email')->nullable(false)->change();
        });
    }
};
