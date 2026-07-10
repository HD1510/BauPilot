<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// M7 (Architekturblatt 4.2/9): Aufgaben & Mängel sowie Projekt-Notizen —
// beide mit client_uuid, damit die JSON-Endpunkte idempotent anlegen können.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('kind'); // task | defect
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_on')->nullable();
            $table->foreignId('assignee_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('done_at')->nullable();
            $table->foreignId('done_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('client_uuid')->nullable();
            $table->unique(['company_id', 'client_uuid']);
            $table->index(['company_id', 'done_at', 'due_on']);
            $table->businessMeta();
        });

        Schema::create('project_notes', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->uuid('client_uuid')->nullable();
            $table->unique(['company_id', 'client_uuid']);
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_notes');
        Schema::dropIfExists('tasks');
    }
};
