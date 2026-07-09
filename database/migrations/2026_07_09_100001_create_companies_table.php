<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('short_code', 8)->unique();
            $table->string('color', 7);
            $table->string('legal_form')->nullable();
            $table->string('address')->nullable();
            $table->string('vat_id')->nullable();
            $table->string('logo_path')->nullable();
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(9);
            $table->decimal('calc_hourly_rate', 12, 2)->nullable();
            $table->unsignedTinyInteger('warranty_years')->default(3);
            $table->timestampTz('archived_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};
