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
        Schema::table('invoices', function (Blueprint $table) {
            // Link to matched bank transaction
            $table->foreignUuid('transaction_id')
                ->nullable()
                ->after('income_source_id')
                ->constrained('transactions')
                ->onDelete('set null');

            // When the match was made
            $table->timestamp('matched_at')->nullable()->after('transaction_id');

            // How the match was made
            $table->enum('match_method', ['auto', 'manual'])->nullable()->after('matched_at');

            // Match confidence score (for auto matches)
            $table->decimal('match_confidence', 5, 2)->nullable()->after('match_method');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropColumn(['transaction_id', 'matched_at', 'match_method', 'match_confidence']);
        });
    }
};
