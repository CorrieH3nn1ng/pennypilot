<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Liabilities table for debt recovery tracking.
     *
     * Examples:
     * - Arrears (municipal, rental, credit card)
     * - Personal loans
     * - Store accounts
     * - Any debt being paid off in installments
     */
    public function up(): void
    {
        Schema::create('liabilities', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();

            // Liability details
            $table->string('name', 100); // e.g., "Municipal Arrears", "Credit Card Debt"
            $table->enum('liability_type', [
                'arrears',      // Overdue payments (municipal, rent, etc.)
                'loan',         // Personal/bank loan
                'credit_card',  // Credit card balance
                'store_account', // Store credit (Edgars, Woolworths, etc.)
                'other'
            ])->default('arrears');

            // Financial tracking
            $table->decimal('original_balance', 12, 2);      // Starting debt amount
            $table->decimal('current_balance', 12, 2);       // Remaining balance
            $table->decimal('monthly_installment', 12, 2);   // Fixed monthly payment
            $table->decimal('interest_rate', 5, 2)->nullable(); // Annual interest rate %

            // Installment tracking
            $table->unsignedInteger('total_installments')->nullable();    // Planned total (if known)
            $table->unsignedInteger('completed_installments')->default(0); // Payments made

            // Status
            $table->enum('status', ['active', 'paid_off', 'paused'])->default('active');
            $table->timestamp('paid_off_at')->nullable();
            $table->decimal('amount_freed', 12, 2)->nullable(); // Monthly amount freed when paid off

            // Metadata
            $table->string('creditor', 100)->nullable();     // Who is owed (bank, municipality, etc.)
            $table->string('reference_number', 50)->nullable(); // Account/reference number
            $table->date('start_date')->nullable();          // When payments started
            $table->date('target_payoff_date')->nullable();  // Target completion date
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Add liability_id to blueprints for linking
        Schema::table('blueprints', function (Blueprint $table) {
            $table->foreignUuid('liability_id')->nullable()->after('category_id')
                ->constrained('liabilities')->nullOnDelete();
        });

        // Add liability tracking to budget_card_items
        Schema::table('budget_card_items', function (Blueprint $table) {
            $table->foreignUuid('liability_id')->nullable()->after('invoice_id')
                ->constrained('liabilities')->nullOnDelete();
            $table->unsignedInteger('installment_number')->nullable()->after('notes');
            $table->decimal('remaining_balance_at_match', 12, 2)->nullable()->after('installment_number');
        });
    }

    public function down(): void
    {
        Schema::table('budget_card_items', function (Blueprint $table) {
            $table->dropForeign(['liability_id']);
            $table->dropColumn(['liability_id', 'installment_number', 'remaining_balance_at_match']);
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropForeign(['liability_id']);
            $table->dropColumn('liability_id');
        });

        Schema::dropIfExists('liabilities');
    }
};
