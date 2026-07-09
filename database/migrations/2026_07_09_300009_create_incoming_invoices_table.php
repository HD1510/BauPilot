<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('incoming_invoices', function (Blueprint $table) {
            $table->id();
            $table->companyOwned();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->string('supplier_invoice_no', 100)->nullable();
            $table->date('invoice_date');
            $table->boolean('date_estimated')->default(false);
            $table->decimal('net', 12, 2);
            $table->decimal('vat_rate', 5, 2);
            $table->decimal('vat', 12, 2);
            $table->decimal('gross', 12, 2);
            $table->boolean('reverse_charge')->default(false);
            $table->foreignId('cost_type_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('payment_method', 50)->nullable();
            $table->date('payment_due_on')->nullable();
            $table->decimal('skonto_amount', 12, 2)->nullable();
            $table->date('skonto_until')->nullable();
            $table->string('payment_status', 20)->default('open');
            $table->date('paid_on')->nullable();
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->boolean('checked')->default(false);
            $table->string('subject', 500)->nullable();
            $table->text('notes')->nullable();
            $table->businessMeta();

            $table->index(['company_id', 'payment_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('incoming_invoices');
    }
};
