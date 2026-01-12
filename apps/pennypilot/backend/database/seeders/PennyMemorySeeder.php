<?php

namespace Database\Seeders;

use App\Models\PennyMemory;
use App\Models\User;
use Illuminate\Database\Seeder;

class PennyMemorySeeder extends Seeder
{
    /**
     * Seed Penny Memory with known user data.
     * Pre-populates Corrie's profile so Penny doesn't ask for known info.
     */
    public function run(): void
    {
        // Find Corrie's user account
        $user = User::where('email', 'corrie.henning@gmail.com')->first();

        if (!$user) {
            $this->command->warn('User corrie.henning@gmail.com not found. Skipping Penny memory seed.');
            return;
        }

        PennyMemory::updateOrCreate(
            ['user_id' => $user->id],
            [
                // Known Identity
                'display_name' => 'Corrie',
                'age' => 53,
                'primary_client' => 'Nucleus Mining',
                'occupation' => 'Freelancer',

                // Boss Goal (Alimony Freedom)
                'boss_goal_name' => 'Alimony Freedom',
                'boss_goal_target' => 1700000.00,
                'boss_goal_current' => 0,
                'boss_goal_deadline' => null,

                // Quadrant Status (will be synced from frontend)
                'has_quests' => false,
                'has_inventory' => false,
                'has_traits' => false,
                'has_destination' => true, // Boss goal is set

                // Conversation flags
                'has_met_penny' => false, // Will be true after first interaction
                'quest_streak' => 0,
                'total_xp' => 0,

                // Default realm
                'current_location' => 'Home Base',
                'target_realm' => 'Alimony Freedom',
                'target_realm_cost' => 1700000.00,

                // Status
                'is_healthy' => true,
                'is_secure' => true,
            ]
        );

        $this->command->info('Penny memory seeded for Corrie.');
    }
}
