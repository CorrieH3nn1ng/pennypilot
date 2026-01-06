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
        // Tax settings for user
        Schema::create('tax_settings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->enum('employment_type', ['employed', 'self_employed', 'mixed'])->default('employed');
            $table->string('tax_number', 20)->nullable(); // SA tax reference number
            $table->boolean('vat_registered')->default(false);
            $table->string('vat_number', 20)->nullable();
            $table->decimal('estimated_annual_income', 12, 2)->nullable();
            $table->decimal('estimated_tax_rate', 5, 2)->nullable(); // Calculated based on bracket
            $table->boolean('provisional_tax_enabled')->default(false);
            $table->integer('tax_year_start_month')->default(3); // March for SA
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique('user_id');
        });

        // Monthly tax provisions
        Schema::create('tax_provisions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('tax_year'); // e.g., 2025 for March 2024 - Feb 2025
            $table->integer('year');
            $table->integer('month');
            $table->decimal('income_amount', 12, 2)->default(0);
            $table->decimal('provision_amount', 12, 2)->default(0);
            $table->decimal('provision_rate', 5, 2)->default(0);
            $table->boolean('is_paid')->default(false);
            $table->date('payment_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'tax_year']);
        });

        // Provisional tax payments (bi-annual)
        Schema::create('provisional_tax_payments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('tax_year');
            $table->enum('payment_period', ['first', 'second', 'top_up']); // Aug, Feb, or top-up
            $table->date('due_date');
            $table->decimal('estimated_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->nullable();
            $table->date('paid_date')->nullable();
            $table->string('reference', 50)->nullable();
            $table->enum('status', ['pending', 'paid', 'overdue'])->default('pending');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'tax_year', 'payment_period']);
        });

        // Tax deductible expenses tracking
        Schema::create('tax_deductions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('tax_year');
            $table->enum('type', [
                'home_office',
                'travel',
                'medical',
                'retirement',
                'professional_development',
                'equipment',
                'other'
            ]);
            $table->string('description', 255);
            $table->decimal('amount', 12, 2);
            $table->date('expense_date');
            $table->string('receipt_path')->nullable(); // For future receipt upload
            $table->uuid('transaction_id')->nullable(); // Link to transaction if imported
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->index(['user_id', 'tax_year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tax_deductions');
        Schema::dropIfExists('provisional_tax_payments');
        Schema::dropIfExists('tax_provisions');
        Schema::dropIfExists('tax_settings');
    }
};
