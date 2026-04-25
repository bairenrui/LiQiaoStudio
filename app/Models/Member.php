<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Member extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'household_id',
        'district_id',
        'member_no',
        'name',
        'name_kana',
        'phone',
        'note',
        'publication_status',
        'membership_status',
        'source_row_no',
        'created_by',
        'updated_by',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function household(): BelongsTo
    {
        return $this->belongsTo(Household::class);
    }

    public function areaLinks(): HasMany
    {
        return $this->hasMany(MapAreaMemberLink::class);
    }
}
