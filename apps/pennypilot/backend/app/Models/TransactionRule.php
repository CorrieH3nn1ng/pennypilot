<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionRule extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'pattern',
        'match_type',
        'category_id',
        'blueprint_id',
        'bucket',
        'is_active',
        'hit_count',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hit_count' => 'integer',
            'priority' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function blueprint(): BelongsTo
    {
        return $this->belongsTo(Blueprint::class);
    }

    /**
     * Check if a description matches this rule
     */
    public function matches(string $description): bool
    {
        $pattern = strtoupper($this->pattern);
        $desc = strtoupper($description);

        return match ($this->match_type) {
            'exact' => $desc === $pattern,
            'starts_with' => str_starts_with($desc, $pattern),
            'contains' => str_contains($desc, $pattern),
            default => false,
        };
    }

    /**
     * Increment hit count when rule is applied
     */
    public function recordHit(): void
    {
        $this->increment('hit_count');
    }

    /**
     * Find matching rule for a transaction description
     */
    public static function findMatchingRule(string $userId, string $description): ?self
    {
        $rules = static::where('user_id', $userId)
            ->where('is_active', true)
            ->orderByDesc('priority')
            ->orderByDesc('hit_count')
            ->get();

        foreach ($rules as $rule) {
            if ($rule->matches($description)) {
                return $rule;
            }
        }

        return null;
    }

    /**
     * Extract a clean anchor pattern from a transaction description.
     *
     * The anchor is the FIRST meaningful word (merchant name) that:
     * - Contains only letters (no numbers or mixed alphanumeric)
     * - Is at least 3 characters long
     *
     * Examples:
     * - "MOMENTUM 01010908391 7466TU" → "MOMENTUM"
     * - "UIM SA 123456" → "UIM"
     * - "PIZZA PERFECT 456789" → "PIZZA"
     * - "C*KOSMOS CAFE BETHAL" → "KOSMOS"
     */
    public static function extractPattern(string $description): string
    {
        // Normalize: convert separators to spaces, uppercase
        $clean = strtoupper($description);
        $clean = preg_replace('/[_\-\.\/\\\\|*#]+/', ' ', $clean);
        $clean = preg_replace('/\s+/', ' ', $clean);

        // Split into words
        $words = explode(' ', trim($clean));

        // Find the FIRST word that is purely alphabetic (the merchant anchor)
        // Skip words that contain numbers or are too short
        $anchor = null;
        foreach ($words as $word) {
            // Skip empty, too short, or contains digits
            if (strlen($word) < 3) continue;
            if (preg_match('/\d/', $word)) continue;

            // Skip common prefixes (C* from card payments, etc.)
            $word = preg_replace('/^[A-Z]\*/', '', $word);
            if (strlen($word) < 3) continue;

            // This is our anchor!
            $anchor = $word;
            break;
        }

        // If no pure alphabetic word found, fall back to first word stripped of numbers
        if (!$anchor) {
            $firstWord = $words[0] ?? '';
            $anchor = preg_replace('/[^A-Z]/', '', $firstWord);
        }

        return $anchor ?: substr(strtoupper($description), 0, 10);
    }
}
