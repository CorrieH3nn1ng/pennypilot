<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accountant Intelligence: Flag transactions for professional review
     *
     * Workflow:
     * 1. User flags transaction with draft question (while memory fresh)
     * 2. Accountant reviews via exportable PDF report
     * 3. User approves as Business (tax-deductible) or rejects to Private
     * 4. Tax reserve auto-recalculates on approval
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Flag status
            $table->boolean('flagged_for_accountant')->default(false)->after('is_transfer');
            $table->enum('accountant_status', ['pending', 'approved_business', 'rejected_private'])
                ->nullable()
                ->after('flagged_for_accountant');

            // Draft question - captured while memory is fresh
            $table->text('accountant_question')->nullable()->after('accountant_status');

            // Review tracking
            $table->timestamp('accountant_reviewed_at')->nullable()->after('accountant_question');
            $table->text('accountant_notes')->nullable()->after('accountant_reviewed_at');

            // Tax impact tracking
            $table->decimal('tax_deduction_amount', 12, 2)->nullable()->after('accountant_notes');

            // Index for quick queries
            $table->index(['user_id', 'flagged_for_accountant', 'accountant_status']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'flagged_for_accountant', 'accountant_status']);
            $table->dropColumn([
                'flagged_for_accountant',
                'accountant_status',
                'accountant_question',
                'accountant_reviewed_at',
                'accountant_notes',
                'tax_deduction_amount',
            ]);
        });
    }
};
