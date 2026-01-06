<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Institutional Financial Standards Migration
 *
 * This migration ensures all financial columns comply with
 * South African accounting standards:
 *
 * 1. DECIMAL(15,2) for all monetary values
 *    - 15 digits total, 2 decimal places
 *    - Supports values up to R9,999,999,999,999.99
 *
 * 2. VAT Registration tracking
 *    - is_vat_registered boolean
 *    - vat_number nullable string
 *    - vat_registration_date for compliance tracking
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        // ========================================
        // 1. Update invoices table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoices ALTER COLUMN subtotal TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE invoices ALTER COLUMN tax_rate TYPE DECIMAL(5,2)');
            DB::statement('ALTER TABLE invoices ALTER COLUMN tax_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE invoices ALTER COLUMN total TYPE DECIMAL(15,2)');
        } else {
            Schema::table('invoices', function (Blueprint $table) {
                $table->decimal('subtotal', 15, 2)->change();
                $table->decimal('tax_rate', 5, 2)->change();
                $table->decimal('tax_amount', 15, 2)->change();
                $table->decimal('total', 15, 2)->change();
            });
        }

        // ========================================
        // 2. Update invoice_items table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN quantity TYPE DECIMAL(15,4)');
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN unit_price TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE invoice_items ALTER COLUMN amount TYPE DECIMAL(15,2)');
        } else {
            Schema::table('invoice_items', function (Blueprint $table) {
                $table->decimal('quantity', 15, 4)->change();
                $table->decimal('unit_price', 15, 2)->change();
                $table->decimal('amount', 15, 2)->change();
            });
        }

        // ========================================
        // 3. Update transactions table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE transactions ALTER COLUMN amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE transactions ALTER COLUMN balance_after TYPE DECIMAL(15,2)');
        } else {
            Schema::table('transactions', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
                $table->decimal('balance_after', 15, 2)->nullable()->change();
            });
        }

        // ========================================
        // 4. Update income_sources table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE income_sources ALTER COLUMN gross_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE income_sources ALTER COLUMN net_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE income_sources ALTER COLUMN tax_amount TYPE DECIMAL(15,2)');
        } else {
            Schema::table('income_sources', function (Blueprint $table) {
                $table->decimal('gross_amount', 15, 2)->change();
                $table->decimal('net_amount', 15, 2)->nullable()->change();
                $table->decimal('tax_amount', 15, 2)->nullable()->change();
            });
        }

        // ========================================
        // 5. Update fixed_expenses table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE fixed_expenses ALTER COLUMN amount TYPE DECIMAL(15,2)');
        } else {
            Schema::table('fixed_expenses', function (Blueprint $table) {
                $table->decimal('amount', 15, 2)->change();
            });
        }

        // ========================================
        // 6. Update budget tables - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE budget_periods ALTER COLUMN total_income TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE budget_periods ALTER COLUMN total_planned TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE budget_periods ALTER COLUMN tax_provision TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE budget_periods ALTER COLUMN net_available TYPE DECIMAL(15,2)');

            DB::statement('ALTER TABLE budget_items ALTER COLUMN planned_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE budget_items ALTER COLUMN actual_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE budget_items ALTER COLUMN rollover_amount TYPE DECIMAL(15,2)');
        } else {
            Schema::table('budget_periods', function (Blueprint $table) {
                $table->decimal('total_income', 15, 2)->change();
                $table->decimal('total_planned', 15, 2)->change();
                $table->decimal('tax_provision', 15, 2)->change();
                $table->decimal('net_available', 15, 2)->change();
            });

            Schema::table('budget_items', function (Blueprint $table) {
                $table->decimal('planned_amount', 15, 2)->change();
                $table->decimal('actual_amount', 15, 2)->change();
                $table->decimal('rollover_amount', 15, 2)->change();
            });
        }

        // ========================================
        // 7. Update tax tables - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE tax_settings ALTER COLUMN estimated_annual_income TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE tax_settings ALTER COLUMN estimated_tax_rate TYPE DECIMAL(5,2)');

            DB::statement('ALTER TABLE tax_provisions ALTER COLUMN income_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE tax_provisions ALTER COLUMN provision_amount TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE tax_provisions ALTER COLUMN provision_rate TYPE DECIMAL(5,2)');
        } else {
            Schema::table('tax_settings', function (Blueprint $table) {
                $table->decimal('estimated_annual_income', 15, 2)->change();
                $table->decimal('estimated_tax_rate', 5, 2)->change();
            });

            Schema::table('tax_provisions', function (Blueprint $table) {
                $table->decimal('income_amount', 15, 2)->change();
                $table->decimal('provision_amount', 15, 2)->change();
                $table->decimal('provision_rate', 5, 2)->change();
            });
        }

        // ========================================
        // 8. Update clients table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE clients ALTER COLUMN default_rate TYPE DECIMAL(15,2)');
        } else {
            Schema::table('clients', function (Blueprint $table) {
                $table->decimal('default_rate', 15, 2)->nullable()->change();
            });
        }

        // ========================================
        // 9. Update accounts table - DECIMAL(15,2)
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE accounts ALTER COLUMN opening_balance TYPE DECIMAL(15,2)');
            DB::statement('ALTER TABLE accounts ALTER COLUMN current_balance TYPE DECIMAL(15,2)');
        } else {
            Schema::table('accounts', function (Blueprint $table) {
                $table->decimal('opening_balance', 15, 2)->change();
                $table->decimal('current_balance', 15, 2)->change();
            });
        }

        // ========================================
        // 10. Add VAT registration fields to business_profiles
        // ========================================
        Schema::table('business_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('business_profiles', 'is_vat_registered')) {
                $table->boolean('is_vat_registered')->default(false)->after('vat_number');
            }
            if (!Schema::hasColumn('business_profiles', 'vat_registration_date')) {
                $table->date('vat_registration_date')->nullable()->after('is_vat_registered');
            }
        });

        // ========================================
        // 11. Add rolling turnover tracking to users
        // ========================================
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'vat_threshold_status')) {
                $table->string('vat_threshold_status', 20)->nullable()->after('is_admin');
            }
            if (!Schema::hasColumn('users', 'vat_threshold_checked_at')) {
                $table->timestamp('vat_threshold_checked_at')->nullable()->after('vat_threshold_status');
            }
        });

        // ========================================
        // 12. Create index for rolling 12-month queries
        // ========================================
        if ($driver === 'pgsql') {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_invoices_paid_turnover ON invoices (user_id, status, paid_date) WHERE status = \'paid\'');
        } else {
            // MySQL doesn't support partial indexes, use regular index
            Schema::table('invoices', function (Blueprint $table) {
                $sm = Schema::getConnection()->getDoctrineSchemaManager();
                $indexes = $sm->listTableIndexes('invoices');
                if (!isset($indexes['idx_invoices_paid_turnover'])) {
                    $table->index(['user_id', 'status', 'paid_date'], 'idx_invoices_paid_turnover');
                }
            });
        }
    }

    public function down(): void
    {
        // Remove added columns
        Schema::table('business_profiles', function (Blueprint $table) {
            $table->dropColumn(['is_vat_registered', 'vat_registration_date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['vat_threshold_status', 'vat_threshold_checked_at']);
        });

        // Drop index
        $driver = DB::connection()->getDriverName();
        if ($driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS idx_invoices_paid_turnover');
        } else {
            Schema::table('invoices', function (Blueprint $table) {
                $table->dropIndex('idx_invoices_paid_turnover');
            });
        }

        // Note: Column type changes are not reverted as they are non-destructive upgrades
    }
};
