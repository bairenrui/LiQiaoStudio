<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapArea extends Model
{
    protected $fillable = [
        'map_version_id',
        'district_id',
        'svg_element_id',
        'area_type',
        'display_name',
        'default_fill_color',
        'highlight_fill_color',
        'is_clickable',
        'has_source_range',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_clickable' => 'boolean',
            'has_source_range' => 'boolean',
        ];
    }

    public function mapVersion(): BelongsTo
    {
        return $this->belongsTo(MapVersion::class);
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function memberLinks(): HasMany
    {
        return $this->hasMany(MapAreaMemberLink::class);
    }
}
