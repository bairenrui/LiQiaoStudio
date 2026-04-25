<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Household extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'district_id',
        'address_lot',
        'building_name',
        'room_no',
        'note',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }
}
