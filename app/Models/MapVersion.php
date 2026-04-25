<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapVersion extends Model
{
    protected $fillable = [
        'name',
        'svg_path',
        'view_box',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(MapArea::class);
    }

    public function layers(): HasMany
    {
        return $this->hasMany(MapLayer::class);
    }
}
