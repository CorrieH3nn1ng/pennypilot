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
        // Budget periods (monthly budgets)
        Schema::create('budget_periods', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->integer('year');
            $table->integer('month');
            $table->enum('methodology', ['50-30-20', 'zero-based', 'envelope', 'pay-yourself-first'])->default('50-30-20');
            $table->decimal('total_income', 12, 2)->default(0);
            $table->decimal('total_planned', 12, 2)->default(0);
            $table->decimal('tax_provision', 12, 2)->default(0);
            $table->decimal('net_available', 12, 2)->default(0);
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->enum('rollover_option', ['savings', 'rollover', 'reset'])->default('reset');
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->unique(['user_id', 'year', 'month']);
            $table->index(['user_id', 'status']);
        });

        // Budget items (planned expenses per category)
        Schema::create('budget_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('budget_period_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 100)->nullable(); // For custom items without category
            $table->decimal('planned_amount', 12, 2)->default(0);
            $table->decimal('actual_amount', 12, 2)->default(0);
            $table->enum('bucket', ['needs', 'wants', 'savings'])->default('needs');
            $table->boolean('is_fixed')->default(false);
            $table->decimal('rollover_amount', 12, 2)->default(0); // Amount rolled from previous month
            $table->timestamps();

            $table->foreign('budget_period_id')->references('id')->on('budget_periods')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['budget_period_id', 'bucket']);
        });

        // Fixed expenses (recurring monthly)
        Schema::create('fixed_expenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->uuid('category_id')->nullable();
            $table->string('name', 100);
            $table->decimal('amount', 12, 2);
            $table->integer('due_day')->nullable(); // Day of month (1-31)
            $table->enum('bucket', ['needs', 'wants', 'savings'])->default('needs');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('budget_items');
        Schema::dropIfExists('fixed_expenses');
        Schema::dropIfExists('budget_periods');
    }
};
