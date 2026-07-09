<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('materials', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('article_no', 100)->nullable();
            $table->string('name');
            $table->decimal('price_net', 12, 2)->nullable();
            $table->string('package_unit', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('materials');
    }
};
