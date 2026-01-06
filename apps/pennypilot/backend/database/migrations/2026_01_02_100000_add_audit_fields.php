<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds audit & reconciliation fields for Blueprint-to-Transaction matching.
     */
    public function up(): void
    {
        // Add blueprint_id to transactions for linking
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignUuid('blueprint_id')
                ->nullable()
                ->after('category_id')
                ->constrained('blueprints')
                ->nullOnDelete();

            // Match metadata
            $table->decimal('match_score', 5, 2)->nullable()->after('confidence_score');
            $table->enum('match_status', ['auto', 'manual', 'unmapped'])->nullable()->after('match_score');

            $table->index(['user_id', 'blueprint_id']);
            $table->index('match_status');
        });

        // Add cancellation tracking to blueprints
        Schema::table('blueprints', function (Blueprint $table) {
            $table->boolean('is_cancelled')->default(false)->after('is_active');
            $table->date('cancelled_at')->nullable()->after('is_cancelled');
            $table->json('match_aliases')->nullable()->after('match_pattern'); // Alternative merchant names

            $table->index(['user_id', 'is_cancelled']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropIndex(['user_id', 'blueprint_id']);
            $table->dropIndex(['match_status']);
            $table->dropColumn(['blueprint_id', 'match_score', 'match_status']);
        });

        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_cancelled']);
            $table->dropColumn(['is_cancelled', 'cancelled_at', 'match_aliases']);
        });
    }
};
