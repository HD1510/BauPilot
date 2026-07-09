<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->string('site_address', 500)->nullable();
            $table->text('description')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('commissioned_on')->nullable();
            $table->date('started_on')->nullable();
            $table->date('planned_finish_on')->nullable();
            $table->date('finished_on')->nullable();
            $table->string('status', 20)->default('open');
            $table->date('warranty_until')->nullable();
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
