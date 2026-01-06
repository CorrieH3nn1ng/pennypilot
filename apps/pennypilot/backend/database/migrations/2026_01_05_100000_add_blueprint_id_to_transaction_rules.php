<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add blueprint_id to transaction_rules for pattern-based blueprint assignment.
     * When user assigns a transaction to a blueprint, we learn the pattern
     * and auto-apply it to all matching transactions.
     */
    public function up(): void
    {
        Schema::table('transaction_rules', function (Blueprint $table) {
            $table->uuid('blueprint_id')->nullable()->after('category_id');
            $table->foreign('blueprint_id')->references('id')->on('blueprints')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_rules', function (Blueprint $table) {
            $table->dropForeign(['blueprint_id']);
            $table->dropColumn('blueprint_id');
        });
    }
};
