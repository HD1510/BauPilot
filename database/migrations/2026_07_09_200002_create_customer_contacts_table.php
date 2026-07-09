<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_contacts', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 100)->nullable();
            $table->string('email')->nullable();
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_contacts');
    }
};
