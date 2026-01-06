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
        Schema::create('budget_cards', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();

            // Period
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month'); // 1-12

            // Status
            $table->enum('status', ['draft', 'active', 'finalized'])->default('draft');
            $table->timestamp('generated_from_blueprint_at')->nullable();

            // Totals (calculated)
            $table->decimal('total_expected_income', 12, 2)->default(0);
            $table->decimal('total_expected_expense', 12, 2)->default(0);
            $table->decimal('total_matched', 12, 2)->default(0);
            $table->decimal('total_leaked', 12, 2)->default(0);
            $table->decimal('total_windfall', 12, 2)->default(0);
            $table->decimal('total_missing', 12, 2)->default(0);

            // Performance
            $table->decimal('success_grade', 5, 2)->nullable(); // 0-100%
            $table->decimal('surplus_amount', 12, 2)->nullable();
            $table->enum('surplus_action', ['savings', 'buffer'])->nullable();
            $table->decimal('rollover_from_previous', 12, 2)->default(0);

            // Finalization
            $table->timestamp('finalized_at')->nullable();

            $table->timestamps();

            // Constraints
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_cards');
    }
};
