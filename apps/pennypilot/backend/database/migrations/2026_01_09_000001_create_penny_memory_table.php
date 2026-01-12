<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penny Memory Table - Stores user context for AI-powered chat
     *
     * This table enables Penny to:
     * 1. Remember user details across sessions
     * 2. Track progress on major goals (boss goals)
     * 3. Maintain inventory of DIY projects/items
     * 4. Avoid asking for data that's already known (no-repeat logic)
     */
    public function up(): void
    {
        Schema::create('penny_memory', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('user_id')->unique();
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // User Identity
            $table->string('display_name')->nullable(); // "Corrie"
            $table->integer('age')->nullable(); // 53
            $table->string('primary_client')->nullable(); // "Nucleus Mining"
            $table->string('occupation')->nullable(); // "Freelancer", "Employee", etc.

            // Boss Goal (The Big Mission)
            $table->string('boss_goal_name')->nullable(); // "Alimony Freedom"
            $table->decimal('boss_goal_target', 15, 2)->nullable(); // 1700000.00
            $table->decimal('boss_goal_current', 15, 2)->default(0); // Current progress
            $table->date('boss_goal_deadline')->nullable();

            // Quadrant Status Checksums (for no-repeat logic)
            $table->boolean('has_quests')->default(false);
            $table->boolean('has_inventory')->default(false);
            $table->boolean('has_traits')->default(false);
            $table->boolean('has_destination')->default(false);

            // DIY Inventory (JSON array of items user is saving for)
            $table->json('inventory_items')->nullable();
            // Example: [{"name": "Car", "alias": "Freedom Machine", "target": 250000, "current": 50000}]

            // Character Traits (JSON array)
            $table->json('character_traits')->nullable();
            // Example: [{"name": "Debt-Free", "progress": 45, "unlocked": false}]

            // Daily Quests (JSON array)
            $table->json('daily_quests')->nullable();
            // Example: [{"name": "Morning Walk", "xp": 20}]

            // Conversation Context
            $table->boolean('has_met_penny')->default(false);
            $table->timestamp('last_interaction')->nullable();
            $table->string('last_briefing_date')->nullable(); // "2026-01-05" for Sunday check
            $table->integer('quest_streak')->default(0);
            $table->integer('total_xp')->default(0);

            // Realm/Location
            $table->string('current_location')->nullable(); // "Home Base"
            $table->string('target_realm')->nullable(); // "Sandton"
            $table->decimal('target_realm_cost', 15, 2)->nullable(); // 500000.00

            // Health & Security flags
            $table->boolean('is_healthy')->default(true);
            $table->boolean('is_secure')->default(true);

            // n8n Integration
            $table->string('n8n_session_id')->nullable(); // For tracking conversation context in n8n
            $table->json('n8n_metadata')->nullable(); // Any additional n8n workflow data

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penny_memory');
    }
};
