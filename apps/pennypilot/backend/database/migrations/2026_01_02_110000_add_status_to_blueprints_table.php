<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add status field and payment holiday dates to blueprints.
     *
     * Status values:
     * - active: Normal recurring item
     * - paused: Payment holiday (temporary suspension)
     * - cancelled: Permanently cancelled (for zombie tracking)
     */
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            // Replace is_active and is_cancelled with unified status enum
            $table->enum('status', ['active', 'paused', 'cancelled'])
                ->default('active')
                ->after('frequency');

            // Payment holiday dates (for paused items)
            $table->date('pause_start_date')->nullable()->after('status');
            $table->date('pause_end_date')->nullable()->after('pause_start_date');
            $table->string('pause_reason', 255)->nullable()->after('pause_end_date');
        });

        // Migrate existing data: is_cancelled=true -> status=cancelled, else active
        \DB::statement("
            UPDATE blueprints
            SET status = CASE
                WHEN is_cancelled = true THEN 'cancelled'
                WHEN is_active = false THEN 'paused'
                ELSE 'active'
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn(['status', 'pause_start_date', 'pause_end_date', 'pause_reason']);
        });
    }
};
