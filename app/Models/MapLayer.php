<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapLayer extends Model
{
    protected $fillable = [
        'map_version_id',
        'key_name',
        'svg_group_id',
        'display_name',
        'is_default_visible',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default_visible' => 'boolean',
        ];
    }

    public function mapVersion(): BelongsTo
    {
        return $this->belongsTo(MapVersion::class);
    }
}
