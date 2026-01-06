<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add 'nature' classification to blueprints for tax purposes.
     *
     * - business: Deductible expense (reduces taxable income before 39.1% + 15% VAT)
     * - private: Personal expense (categorized into 50/30/20 framework)
     */
    public function up(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->enum('nature', ['business', 'private'])
                ->default('private')
                ->after('bucket')
                ->comment('Business expenses are tax-deductible; Private follow 50/30/20');
        });
    }

    public function down(): void
    {
        Schema::table('blueprints', function (Blueprint $table) {
            $table->dropColumn('nature');
        });
    }
};
