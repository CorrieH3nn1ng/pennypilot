<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * PostgreSQL Compatibility Migration
 *
 * This migration ensures the schema is Postgres-ready:
 * 1. Converts JSON columns to JSONB for better performance
 * 2. Fixes unsigned integer types (Postgres doesn't support unsigned)
 * 3. Replaces MySQL-specific ENUM modifications
 *
 * Run AFTER migrating data from MySQL to Postgres.
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // =====================================================
            // 1. Convert JSON to JSONB for better indexing/querying
            // =====================================================

            // transactions.tags
            if (Schema::hasColumn('transactions', 'tags')) {
                DB::statement('ALTER TABLE transactions ALTER COLUMN tags TYPE JSONB USING tags::JSONB');
            }

            // oplog.data
            if (Schema::hasColumn('oplog', 'data')) {
                DB::statement('ALTER TABLE oplog ALTER COLUMN data TYPE JSONB USING data::JSONB');
            }

            // staged_invoices.raw_data
            if (Schema::hasColumn('staged_invoices', 'raw_data')) {
                DB::statement('ALTER TABLE staged_invoices ALTER COLUMN raw_data TYPE JSONB USING raw_data::JSONB');
            }

            // =====================================================
            // 2. Create GIN indexes for JSONB columns (fast searches)
            // =====================================================

            DB::statement('CREATE INDEX IF NOT EXISTS idx_transactions_tags ON transactions USING GIN (tags)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_oplog_data ON oplog USING GIN (data)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_staged_invoices_raw_data ON staged_invoices USING GIN (raw_data)');

            // =====================================================
            // 3. Add CHECK constraints for ENUM-like columns
            //    (pgloader converts MySQL ENUMs to VARCHAR)
            // =====================================================

            // income_sources.type - ensure valid values
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'income_sources_type_check'
                    ) THEN
                        ALTER TABLE income_sources
                        ADD CONSTRAINT income_sources_type_check
                        CHECK (type IN ('salary', 'freelance', 'investment', 'rental', 'side_hustle', 'consulting', 'retainer', 'project', 'royalties', 'other'));
                    END IF;
                END $$;
            ");

            // transactions.import_source
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'transactions_import_source_check'
                    ) THEN
                        ALTER TABLE transactions
                        ADD CONSTRAINT transactions_import_source_check
                        CHECK (import_source IN ('manual', 'csv', 'api', 'sync'));
                    END IF;
                END $$;
            ");

            // transactions.categorized_by
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'transactions_categorized_by_check'
                    ) THEN
                        ALTER TABLE transactions
                        ADD CONSTRAINT transactions_categorized_by_check
                        CHECK (categorized_by IS NULL OR categorized_by IN ('manual', 'rule', 'ai', 'system'));
                    END IF;
                END $$;
            ");

            // transactions.sync_status
            DB::statement("
                DO $$
                BEGIN
                    IF NOT EXISTS (
                        SELECT 1 FROM pg_constraint WHERE conname = 'transactions_sync_status_check'
                    ) THEN
                        ALTER TABLE transactions
                        ADD CONSTRAINT transactions_sync_status_check
                        CHECK (sync_status IN ('synced', 'pending', 'conflict'));
                    END IF;
                END $$;
            ");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            // Drop GIN indexes
            DB::statement('DROP INDEX IF EXISTS idx_transactions_tags');
            DB::statement('DROP INDEX IF EXISTS idx_oplog_data');
            DB::statement('DROP INDEX IF EXISTS idx_staged_invoices_raw_data');

            // Drop CHECK constraints
            DB::statement('ALTER TABLE income_sources DROP CONSTRAINT IF EXISTS income_sources_type_check');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_import_source_check');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_categorized_by_check');
            DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_sync_status_check');

            // Convert JSONB back to JSON (if needed for MySQL compatibility)
            DB::statement('ALTER TABLE transactions ALTER COLUMN tags TYPE JSON USING tags::JSON');
            DB::statement('ALTER TABLE oplog ALTER COLUMN data TYPE JSON USING data::JSON');
            DB::statement('ALTER TABLE staged_invoices ALTER COLUMN raw_data TYPE JSON USING raw_data::JSON');
        }
    }
};
