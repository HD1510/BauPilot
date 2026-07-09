<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('change_orders', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('amount_net', 12, 2)->nullable();
            $table->string('status', 20)->default('requested');
            $table->foreignId('outgoing_invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('change_orders');
    }
};
