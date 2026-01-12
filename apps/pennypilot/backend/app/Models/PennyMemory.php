<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PennyMemory extends Model
{
    use HasUuids;

    protected $table = 'penny_memory';

    protected $fillable = [
        'user_id',
        // Identity
        'display_name',
        'age',
        'primary_client',
        'occupation',
        // Boss Goal
        'boss_goal_name',
        'boss_goal_target',
        'boss_goal_current',
        'boss_goal_deadline',
        // Quadrant Checksums
        'has_quests',
        'has_inventory',
        'has_traits',
        'has_destination',
        // JSON Data
        'inventory_items',
        'character_traits',
        'daily_quests',
        // Conversation
        'has_met_penny',
        'last_interaction',
        'last_briefing_date',
        'quest_streak',
        'total_xp',
        // Realm
        'current_location',
        'target_realm',
        'target_realm_cost',
        // Status
        'is_healthy',
        'is_secure',
        // n8n
        'n8n_session_id',
        'n8n_metadata',
    ];

    protected $casts = [
        'age' => 'integer',
        'boss_goal_target' => 'decimal:2',
        'boss_goal_current' => 'decimal:2',
        'boss_goal_deadline' => 'date',
        'has_quests' => 'boolean',
        'has_inventory' => 'boolean',
        'has_traits' => 'boolean',
        'has_destination' => 'boolean',
        'inventory_items' => 'array',
        'character_traits' => 'array',
        'daily_quests' => 'array',
        'has_met_penny' => 'boolean',
        'last_interaction' => 'datetime',
        'quest_streak' => 'integer',
        'total_xp' => 'integer',
        'target_realm_cost' => 'decimal:2',
        'is_healthy' => 'boolean',
        'is_secure' => 'boolean',
        'n8n_metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get fields that are NULL (unknown to Penny)
     * Used for no-repeat logic - Penny should ask about these
     */
    public function getMissingFields(): array
    {
        $missing = [];

        // Identity fields
        if (empty($this->display_name)) $missing[] = 'display_name';
        if (empty($this->age)) $missing[] = 'age';
        if (empty($this->primary_client)) $missing[] = 'primary_client';

        // Boss goal
        if (empty($this->boss_goal_name)) $missing[] = 'boss_goal';

        // Quadrant status
        if (!$this->has_quests) $missing[] = 'quests';
        if (!$this->has_inventory) $missing[] = 'inventory';
        if (!$this->has_traits) $missing[] = 'traits';
        if (!$this->has_destination) $missing[] = 'destination';

        return $missing;
    }

    /**
     * Get complete context for n8n webhook
     */
    public function getN8nContext(): array
    {
        return [
            'user_id' => $this->user_id,
            'identity' => [
                'name' => $this->display_name,
                'age' => $this->age,
                'client' => $this->primary_client,
                'occupation' => $this->occupation,
            ],
            'boss_goal' => [
                'name' => $this->boss_goal_name,
                'target' => $this->boss_goal_target,
                'current' => $this->boss_goal_current,
                'progress_percent' => $this->boss_goal_target > 0
                    ? round(($this->boss_goal_current / $this->boss_goal_target) * 100, 1)
                    : 0,
                'deadline' => $this->boss_goal_deadline?->format('Y-m-d'),
            ],
            'quadrants' => [
                'has_quests' => $this->has_quests,
                'has_inventory' => $this->has_inventory,
                'has_traits' => $this->has_traits,
                'has_destination' => $this->has_destination,
            ],
            'data' => [
                'quests' => $this->daily_quests ?? [],
                'inventory' => $this->inventory_items ?? [],
                'traits' => $this->character_traits ?? [],
            ],
            'stats' => [
                'total_xp' => $this->total_xp,
                'quest_streak' => $this->quest_streak,
                'level' => floor($this->total_xp / 500) + 1,
            ],
            'realm' => [
                'current' => $this->current_location,
                'target' => $this->target_realm,
                'target_cost' => $this->target_realm_cost,
            ],
            'status' => [
                'healthy' => $this->is_healthy,
                'secure' => $this->is_secure,
            ],
            'conversation' => [
                'has_met_penny' => $this->has_met_penny,
                'last_interaction' => $this->last_interaction?->toIso8601String(),
                'last_briefing' => $this->last_briefing_date,
                'session_id' => $this->n8n_session_id,
            ],
            'missing_fields' => $this->getMissingFields(),
        ];
    }

    /**
     * Update quadrant checksums based on data
     */
    public function syncQuadrantChecksums(): void
    {
        $this->has_quests = !empty($this->daily_quests) && count($this->daily_quests) > 0;
        $this->has_inventory = !empty($this->inventory_items) && count($this->inventory_items) > 0;
        $this->has_traits = !empty($this->character_traits) && count($this->character_traits) > 0;
        $this->has_destination = !empty($this->target_realm) && $this->target_realm !== 'Sandton';
    }
}
