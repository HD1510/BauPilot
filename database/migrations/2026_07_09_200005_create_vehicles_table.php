<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('plate', 20);
            $table->string('brand', 100)->nullable();
            $table->string('model', 100)->nullable();
            $table->date('inspection_due_on')->nullable();
            $table->date('vignette_until')->nullable();
            $table->string('fuel_card', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->unique(['company_id', 'plate']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicles');
    }
};
