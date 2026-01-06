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
        Schema::create('budget_card_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('budget_card_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('blueprint_id')->nullable()->constrained()->nullOnDelete(); // null for one-offs
            $table->foreignUuid('invoice_id')->nullable()->constrained()->nullOnDelete(); // for income items
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();

            // Item details
            $table->enum('item_type', ['income', 'expense'])->default('expense');
            $table->string('name', 100);
            $table->decimal('expected_amount', 12, 2);
            $table->unsignedTinyInteger('expected_day')->nullable(); // 1-31
            $table->enum('bucket', ['needs', 'wants', 'savings'])->default('needs');

            // Matching configuration
            $table->string('match_pattern', 100)->nullable();
            $table->enum('match_type', ['contains', 'starts_with', 'exact'])->default('contains');

            // Status flags
            $table->boolean('is_one_off')->default(false); // true for month-specific items

            // Audit results
            $table->enum('audit_status', ['pending', 'matched', 'missing'])->default('pending');
            $table->foreignUuid('matched_transaction_id')->nullable()->constrained('transactions')->nullOnDelete();
            $table->decimal('matched_amount', 12, 2)->nullable();
            $table->date('matched_date')->nullable();
            $table->decimal('match_confidence', 5, 2)->nullable(); // 0-100%

            $table->text('notes')->nullable();
            $table->timestamps();

            // Indexes
            $table->index(['budget_card_id', 'audit_status']);
            $table->index(['budget_card_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_card_items');
    }
};
