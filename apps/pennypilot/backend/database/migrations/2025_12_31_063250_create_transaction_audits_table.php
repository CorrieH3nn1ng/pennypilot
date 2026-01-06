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
        Schema::create('transaction_audits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_card_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('budget_card_item_id')->nullable()->constrained()->nullOnDelete();

            // Classification
            $table->enum('classification', ['matched', 'leak', 'windfall', 'transfer'])->default('leak');

            // Matching details
            $table->enum('match_method', ['auto', 'manual'])->nullable();
            $table->decimal('match_confidence', 5, 2)->nullable(); // 0-100%

            // Flags
            $table->boolean('is_silenced')->default(false); // user acknowledged, hide from alerts

            $table->timestamp('created_at')->useCurrent();

            // Constraints
            $table->unique(['budget_card_id', 'transaction_id']);
            $table->index(['budget_card_id', 'classification']);
        });

        // Add audit_status to transactions table
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('audit_status', ['pending', 'audited', 'ignored'])->default('pending')->after('is_categorized');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('audit_status');
        });
        Schema::dropIfExists('transaction_audits');
    }
};
