<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ConversionFile extends Model
{
    protected $fillable = [
        'conversion_id', 'type',
        'original_name', 'path', 'disk', 'size',
    ];

    public function conversion(): BelongsTo
    {
        return $this->belongsTo(Conversion::class);
    }

    public function fullPath(): string
    {
        return Storage::path($this->path);
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function sizeForHumans(): string
    {
        $size = $this->size ?? 0;
        if ($size < 1024) return "{$size} B";
        if ($size < 1048576) return round($size / 1024, 1) . ' KB';
        return round($size / 1048576, 1) . ' MB';
    }
}
