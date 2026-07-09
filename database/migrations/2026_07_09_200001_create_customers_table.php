<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('name');
            $table->string('normalized_name')->index();
            $table->string('address', 500)->nullable();
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->string('vat_id', 50)->nullable();
            $table->unsignedSmallInteger('payment_target_days')->default(14);
            $table->string('external_ref')->nullable();
            $table->text('notes')->nullable();
            $table->softDeletesTz();
            $table->businessMeta();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
