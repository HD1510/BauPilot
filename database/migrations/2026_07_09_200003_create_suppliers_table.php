<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('short_code', 20)->nullable();
            $table->unsignedSmallInteger('payment_target_days')->default(30);
            $table->foreignId('default_cost_type_id')->nullable()->constrained('cost_types')->nullOnDelete();
            $table->decimal('skonto_percent', 5, 2)->nullable();
            $table->unsignedSmallInteger('skonto_days')->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->unique(['company_id', 'short_code']);
            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
