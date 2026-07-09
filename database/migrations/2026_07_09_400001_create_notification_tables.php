<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Benachrichtigungswahl je Benutzer und Firma (Architekturblatt 4.3)
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('daily_email')->default(true);
            $table->boolean('push')->default(false);
            $table->timestampsTz();

            $table->unique(['user_id', 'company_id']);
        });

        // Web-Push-Abos (Versand folgt; Tabelle laut Katalog 1A)
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('endpoint', 500)->unique();
            $table->string('public_key');
            $table->string('auth_token');
            $table->timestampsTz();
        });

        // Schutz vor Doppelversand (Architekturblatt Abschnitt 5/7)
        Schema::create('notification_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 50)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('kind', 50);
            $table->date('sent_on');
            $table->timestampsTz();

            $table->index(['company_id', 'user_id', 'kind', 'sent_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_log');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('notification_settings');
    }
};
