<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // POPI Act compliance fields
            $table->boolean('consent_given')->default(false)->after('password');
            $table->timestamp('consent_date')->nullable()->after('consent_given');
            $table->boolean('data_retention_agreed')->default(false)->after('consent_date');

            // Encryption key (encrypted with master key)
            $table->text('encryption_key_encrypted')->nullable()->after('data_retention_agreed');

            // Soft deletes for data retention
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'consent_given',
                'consent_date',
                'data_retention_agreed',
                'encryption_key_encrypted',
                'deleted_at',
            ]);
        });
    }
};
