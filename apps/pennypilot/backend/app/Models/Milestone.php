<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Milestone extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'due_date',
        'type',
        'status',
        'priority',
        'category',
        'meta',
        'completed_at',
        'dismissed_at',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    // Relationships

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Scopes

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeUpcoming($query, int $days = 30)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<=', Carbon::now()->addDays($days))
            ->where('due_date', '>=', Carbon::now());
    }

    public function scopeOverdue($query)
    {
        return $query->where('status', 'pending')
            ->where('due_date', '<', Carbon::now());
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    // Accessors

    public function getIsOverdueAttribute(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }

    public function getDaysUntilDueAttribute(): int
    {
        return Carbon::now()->startOfDay()->diffInDays($this->due_date, false);
    }

    public function getIsUrgentAttribute(): bool
    {
        return $this->status === 'pending' && $this->days_until_due <= 7;
    }

    public function getPriorityColorAttribute(): string
    {
        return match ($this->priority) {
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'blue',
            'low' => 'grey',
            default => 'grey',
        };
    }

    public function getTypeIconAttribute(): string
    {
        return match ($this->type) {
            'reminder' => 'notifications',
            'review' => 'rate_review',
            'payment' => 'payment',
            'goal' => 'flag',
            default => 'event',
        };
    }

    // Methods

    public function complete(): self
    {
        $this->status = 'completed';
        $this->completed_at = Carbon::now();
        $this->save();
        return $this;
    }

    public function dismiss(): self
    {
        $this->status = 'dismissed';
        $this->dismissed_at = Carbon::now();
        $this->save();
        return $this;
    }

    public function snooze(int $days = 7): self
    {
        $this->due_date = Carbon::now()->addDays($days);
        $this->save();
        return $this;
    }
}
