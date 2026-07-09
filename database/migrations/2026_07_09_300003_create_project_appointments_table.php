<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_appointments', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->date('on_date');
            $table->string('label');
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_appointments');
    }
};
