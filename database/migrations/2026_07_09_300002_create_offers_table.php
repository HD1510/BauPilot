<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->string('location', 500)->nullable();
            $table->text('description')->nullable();
            $table->string('status', 30)->default('inquiry');
            $table->date('viewing_on')->nullable();
            $table->date('follow_up_on')->nullable();
            $table->string('offer_number', 50)->nullable();
            $table->decimal('offer_amount_net', 12, 2)->nullable();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
