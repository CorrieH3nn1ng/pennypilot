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
        Schema::create('business_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->unique()->constrained()->onDelete('cascade');

            // Business Info
            $table->string('business_name')->nullable(); // Could be personal name for freelancers
            $table->string('trading_name')->nullable();  // "Trading as"
            $table->string('registration_number')->nullable(); // Company registration
            $table->string('tax_number')->nullable();    // SARS tax reference
            $table->string('vat_number')->nullable();    // VAT number if registered

            // Contact Info
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();
            $table->text('address')->nullable();

            // Banking Details (for invoices)
            $table->string('bank_name')->nullable();
            $table->string('bank_branch')->nullable();
            $table->string('bank_branch_code')->nullable();
            $table->string('account_holder')->nullable();
            $table->string('account_number')->nullable();
            $table->string('account_type')->nullable(); // cheque, savings, transmission

            // Invoice Settings
            $table->string('invoice_prefix')->default('INV-'); // Prefix for invoice numbers
            $table->text('invoice_notes')->nullable();  // Default notes on invoices
            $table->text('payment_terms')->nullable();  // e.g., "Payment due within 30 days"
            $table->decimal('default_tax_rate', 5, 2)->default(0); // Default VAT rate

            // Logo
            $table->string('logo_path')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('business_profiles');
    }
};
