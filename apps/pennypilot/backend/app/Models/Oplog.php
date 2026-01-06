<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Oplog extends Model
{
    use HasUuids;

    protected $table = 'oplog';

    protected $fillable = [
        'user_id',
        'client_timestamp',
        'entity_type',
        'entity_id',
        'operation',
        'data',
        'checksum',
        'status',
        'error_message',
        'server_version',
    ];

    protected $casts = [
        'client_timestamp' => 'integer',
        'data' => 'array',
        'server_version' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for pending operations
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for applied operations
     */
    public function scopeApplied($query)
    {
        return $query->where('status', 'applied');
    }

    /**
     * Scope for rejected operations
     */
    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
