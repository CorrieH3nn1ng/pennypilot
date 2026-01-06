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
        Schema::table('clients', function (Blueprint $table) {
            // Billing period settings (smallInteger works in both MySQL and Postgres)
            $table->smallInteger('billing_period_start')->default(1)->after('notes'); // Day of month (1-31)
            $table->smallInteger('billing_period_end')->nullable()->after('billing_period_start'); // Day of month, null = last day

            // Payment settings
            $table->smallInteger('payment_day')->nullable()->after('billing_period_end'); // Day of month for payment run
            $table->smallInteger('payment_terms')->nullable()->after('payment_day'); // Days from invoice date (e.g., 30)

            // Default hourly/daily rate for this client
            $table->decimal('default_rate', 10, 2)->nullable()->after('payment_terms');
            $table->string('rate_type', 10)->nullable()->after('default_rate'); // hourly, daily, fixed
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn([
                'billing_period_start',
                'billing_period_end',
                'payment_day',
                'payment_terms',
                'default_rate',
                'rate_type',
            ]);
        });
    }
};
