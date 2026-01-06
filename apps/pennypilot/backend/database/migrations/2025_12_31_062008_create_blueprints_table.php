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
        Schema::create('blueprints', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('category_id')->nullable()->constrained()->nullOnDelete();

            // Item details
            $table->string('name', 100);
            $table->decimal('expected_amount', 12, 2);
            $table->decimal('amount_tolerance', 5, 2)->default(5.00); // 5% default
            $table->unsignedTinyInteger('due_day')->nullable(); // 1-31
            $table->unsignedTinyInteger('due_day_tolerance')->default(3); // days before/after

            // Classification
            $table->enum('item_type', ['income', 'expense'])->default('expense');
            $table->enum('bucket', ['needs', 'wants', 'savings'])->default('needs');

            // Matching configuration
            $table->string('match_pattern', 100)->nullable();
            $table->enum('match_type', ['contains', 'starts_with', 'exact'])->default('contains');

            // Scheduling
            $table->enum('frequency', ['monthly', 'weekly', 'quarterly', 'annual'])->default('monthly');

            // Status
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('priority')->default(0); // higher = checked first
            $table->text('notes')->nullable();

            $table->timestamps();

            // Indexes
            $table->index(['user_id', 'is_active']);
            $table->index(['user_id', 'item_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blueprints');
    }
};
