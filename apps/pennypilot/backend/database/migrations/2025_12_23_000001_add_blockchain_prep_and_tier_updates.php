<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * This migration prepares the database for:
     * 1. Blockchain/Notary integration (metadata_hash on transactions)
     * 2. New subscription tiers (free, cruiser, captain)
     * 3. Oplog sync tracking
     */
    public function up(): void
    {
        // Add metadata_hash to transactions for future blockchain integration
        Schema::table('transactions', function (Blueprint $table) {
            // Hash of transaction data for integrity verification (Merkle tree ready)
            $table->string('metadata_hash', 64)->nullable()->after('version');
            // Timestamp when hash was computed
            $table->timestamp('hash_computed_at')->nullable()->after('metadata_hash');
            // If anchored to blockchain, the block reference
            $table->string('blockchain_anchor', 128)->nullable()->after('hash_computed_at');
            $table->timestamp('anchored_at')->nullable()->after('blockchain_anchor');
        });

        // Update subscription_tier to include new tiers
        // Note: MySQL ENUM modification - need to recreate the column
        Schema::table('users', function (Blueprint $table) {
            // Drop old column and add new one with updated enum
            $table->string('subscription_tier', 20)->default('free')->change();
        });

        // Create oplog table for server-side tracking
        Schema::create('oplog', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->bigInteger('client_timestamp'); // Client's timestamp
            $table->string('entity_type', 50); // transaction, invoice, etc.
            $table->uuid('entity_id');
            $table->string('operation', 20); // create, update, delete
            $table->json('data'); // The mutation data
            $table->string('checksum', 32)->nullable(); // Client-computed checksum
            $table->string('status', 20)->default('pending'); // pending, applied, rejected
            $table->text('error_message')->nullable();
            $table->integer('server_version')->nullable(); // For conflict detection
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['entity_type', 'entity_id']);
            $table->index('client_timestamp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn([
                'metadata_hash',
                'hash_computed_at',
                'blockchain_anchor',
                'anchored_at',
            ]);
        });

        Schema::dropIfExists('oplog');
    }
};
