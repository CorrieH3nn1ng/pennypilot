<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Add income_type column to differentiate between:
     * - 'self_employed': Freelancers/sole proprietors (full invoicing, VAT tracking)
     * - 'salaried': Employed users (expense tracking, payslip focus)
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('income_type', 20)->default('self_employed')->after('subscription_tier');
            $table->decimal('net_monthly_income', 15, 2)->nullable()->after('income_type');
        });

        // Add check constraint for valid income types (PostgreSQL)
        DB::statement("ALTER TABLE users ADD CONSTRAINT check_income_type CHECK (income_type IN ('self_employed', 'salaried'))");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove check constraint first
        DB::statement("ALTER TABLE users DROP CONSTRAINT IF EXISTS check_income_type");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['income_type', 'net_monthly_income']);
        });
    }
};
