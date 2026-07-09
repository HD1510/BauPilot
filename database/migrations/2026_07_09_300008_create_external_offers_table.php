<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('external_offers', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->decimal('amount_net', 12, 2)->nullable();
            $table->date('received_on')->nullable();
            $table->date('valid_until')->nullable();
            $table->string('status', 20)->default('received');
            $table->text('notes')->nullable();
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_offers');
    }
};
