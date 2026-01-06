<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->foreignUuid('client_id')->constrained()->onDelete('cascade');

            // Invoice number format: YYMMDDCCC-NNN
            // YY = year, MM = month, DD = day payable, CCC = client code, NNN = sequence
            $table->string('invoice_number', 20)->unique();
            $table->integer('sequence_number'); // The NNN part

            // Status: draft, sent, paid, overdue, cancelled
            $table->enum('status', ['draft', 'sent', 'paid', 'overdue', 'cancelled'])->default('draft');

            // Invoice type: regular (generated) or historical (uploaded)
            $table->enum('invoice_type', ['regular', 'historical'])->default('regular');

            // Dates
            $table->date('invoice_date');
            $table->date('due_date');
            $table->date('paid_date')->nullable();

            // Amounts
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('tax_rate', 5, 2)->default(0); // VAT rate (e.g., 15%)
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            // Description/notes
            $table->string('title')->nullable(); // Brief description (e.g., "Monthly Contract")
            $table->text('notes')->nullable();

            // Historical invoice file (PDF upload)
            $table->string('uploaded_file_path')->nullable();

            // Link to income when paid
            $table->foreignUuid('income_source_id')->nullable()->constrained('income_sources')->onDelete('set null');

            $table->timestamps();

            // Index for faster queries
            $table->index(['user_id', 'status']);
            $table->index(['user_id', 'invoice_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
