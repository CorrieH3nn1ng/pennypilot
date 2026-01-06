<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'nature' classification to invoices for tax purposes.
     *
     * - business: Income from business activities (subject to VAT + income tax)
     * - private: Personal income (if applicable)
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->enum('nature', ['business', 'private'])
                ->default('business')
                ->after('invoice_type')
                ->comment('Business invoices are VAT-inclusive income; Private for personal');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }
};
