<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionMessage extends Model
{
    protected $fillable = [
        'conversion_id', 'role', 'type', 'content',
    ];

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }

    public function isFromAi(): bool
    {
        return $this->role === 'ai';
    }

    public function isFromUser(): bool
    {
        return $this->role === 'user';
    }

    public function isSystem(): bool
    {
        return $this->role === 'system';
    }
}
