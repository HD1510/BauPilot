<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vehicle_dates', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('due_on');
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vehicle_dates');
    }
};
