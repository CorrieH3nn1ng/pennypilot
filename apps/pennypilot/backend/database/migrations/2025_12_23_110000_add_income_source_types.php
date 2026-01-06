<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Add new income source types for provisional taxpayers:
     * - consulting: Consulting work
     * - retainer: Retainer agreements
     * - project: Project-based work
     * - royalties: Royalty income
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // MySQL: Modify the enum to include new types
            DB::statement("ALTER TABLE income_sources MODIFY COLUMN type ENUM(
                'salary',
                'freelance',
                'investment',
                'rental',
                'side_hustle',
                'consulting',
                'retainer',
                'project',
                'royalties',
                'other'
            ) NOT NULL");
        } elseif ($driver === 'pgsql') {
            // PostgreSQL: Convert to VARCHAR and add CHECK constraint
            // First, change column type to VARCHAR if it's an ENUM
            DB::statement("ALTER TABLE income_sources ALTER COLUMN type TYPE VARCHAR(20)");

            // Add CHECK constraint for valid values
            DB::statement("ALTER TABLE income_sources DROP CONSTRAINT IF EXISTS income_sources_type_check");
            DB::statement("ALTER TABLE income_sources ADD CONSTRAINT income_sources_type_check CHECK (
                type IN ('salary', 'freelance', 'investment', 'rental', 'side_hustle', 'consulting', 'retainer', 'project', 'royalties', 'other')
            )");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'mysql') {
            // Revert to original types (note: this will fail if new types are in use)
            DB::statement("ALTER TABLE income_sources MODIFY COLUMN type ENUM(
                'salary',
                'freelance',
                'investment',
                'rental',
                'side_hustle',
                'other'
            ) NOT NULL");
        } elseif ($driver === 'pgsql') {
            // Update CHECK constraint to original values
            DB::statement("ALTER TABLE income_sources DROP CONSTRAINT IF EXISTS income_sources_type_check");
            DB::statement("ALTER TABLE income_sources ADD CONSTRAINT income_sources_type_check CHECK (
                type IN ('salary', 'freelance', 'investment', 'rental', 'side_hustle', 'other')
            )");
        }
    }
};
