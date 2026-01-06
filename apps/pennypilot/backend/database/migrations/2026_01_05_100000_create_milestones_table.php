<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('milestones', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->onDelete('cascade');
            $table->string('title');
            $table->text('description')->nullable();
            $table->date('due_date');
            $table->string('type')->default('reminder'); // reminder, review, payment, goal
            $table->string('status')->default('pending'); // pending, completed, dismissed
            $table->string('priority')->default('medium'); // low, medium, high, critical
            $table->string('category')->nullable(); // budget, investment, tax, etc.
            $table->json('meta')->nullable(); // Additional data like blueprint_ids, amounts, etc.
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'due_date']);
            $table->index(['user_id', 'status', 'due_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('milestones');
    }
};
