<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->uuid('category_id')->nullable();

            // Transaction data
            $table->date('transaction_date');
            $table->string('description', 500);
            $table->decimal('amount', 15, 2); // Negative for expenses, positive for income
            $table->decimal('balance_after', 15, 2)->nullable();

            // Bank import metadata
            $table->string('bank_reference', 100)->nullable();
            $table->enum('import_source', ['manual', 'csv', 'api', 'sync'])->default('manual');
            $table->text('raw_description')->nullable();

            // Categorization
            $table->boolean('is_categorized')->default(false);
            $table->enum('categorized_by', ['manual', 'rule', 'ai', 'system'])->nullable();
            $table->decimal('confidence_score', 3, 2)->nullable();

            // Notes and tags
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Sync tracking
            $table->string('local_id', 100)->nullable()->index();
            $table->enum('sync_status', ['synced', 'pending', 'conflict'])->default('synced');
            $table->timestamp('last_synced_at')->nullable();
            $table->integer('version')->default(1);

            // Encryption flag (for POPI compliance)
            $table->boolean('is_encrypted')->default(false);

            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['user_id', 'transaction_date']);
            $table->index('sync_status');
            $table->unique(['user_id', 'bank_reference']);

            $table->foreign('category_id')->references('id')->on('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
