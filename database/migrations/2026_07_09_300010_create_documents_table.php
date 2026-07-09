<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->morphs('documentable');
            $table->string('category', 20);
            $table->string('original_name');
            $table->string('path', 500);
            $table->unsignedBigInteger('size');
            $table->string('mime', 100);
            $table->uuid('client_uuid')->nullable();
            $table->businessMeta();

            $table->unique(['company_id', 'client_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
