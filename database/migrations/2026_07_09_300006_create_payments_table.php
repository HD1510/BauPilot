<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('outgoing_invoice_id')->constrained()->restrictOnDelete();
            $table->date('paid_on');
            $table->decimal('amount', 12, 2);
            // Gesetzt = Freigabe genau dieses Einbehalts (v1.1).
            $table->foreignId('retention_id')->nullable()->constrained()->restrictOnDelete();
            $table->businessMeta();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
