<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fix confidence_score precision to allow 0-100 values.
     *
     * Original: decimal(3,2) = max 9.99
     * New: decimal(5,2) = max 999.99
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('confidence_score', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('confidence_score', 3, 2)->nullable()->change();
        });
    }
};
