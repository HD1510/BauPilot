<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outgoing_invoices', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->string('doc_type', 20)->default('invoice');
            $table->string('number', 50);
            $table->date('invoice_date');
            $table->date('due_on');
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('original_invoice_id')->nullable()->constrained('outgoing_invoices')->restrictOnDelete();
            $table->foreignId('final_invoice_id')->nullable()->constrained('outgoing_invoices')->nullOnDelete();
            $table->decimal('net', 12, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('gross', 12, 2);
            $table->string('zero_rate_reason', 30)->nullable();
            $table->string('payment_status', 20)->default('open');
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->unique(['company_id', 'number']);
            $table->index(['company_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outgoing_invoices');
    }
};
