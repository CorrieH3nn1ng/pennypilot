<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Transaction rules for automatic categorization and bucket assignment.
     * Created when user assigns a transaction via Penny Interrogator.
     */
    public function up(): void
    {
        Schema::create('transaction_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id');
            $table->string('pattern', 100); // The keyword/pattern to match
            $table->enum('match_type', ['contains', 'starts_with', 'exact'])->default('contains');
            $table->uuid('category_id')->nullable();
            $table->enum('bucket', ['needs', 'wants', 'savings'])->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('hit_count')->default(0); // How many times rule was applied
            $table->integer('priority')->default(0); // Higher = checked first
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('categories')->onDelete('set null');
            $table->index(['user_id', 'is_active']);
            $table->unique(['user_id', 'pattern', 'match_type']);
        });

        // Add bucket column to transactions for direct bucket assignment
        Schema::table('transactions', function (Blueprint $table) {
            $table->enum('bucket', ['needs', 'wants', 'savings'])->nullable()->after('category_id');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('bucket');
        });
        Schema::dropIfExists('transaction_rules');
    }
};
