<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class District extends Model
{
    protected $fillable = [
        'code',
        'district_no',
        'block_code',
        'display_name',
        'sort_order',
    ];

    public function mapAreas(): HasMany
    {
        return $this->hasMany(MapArea::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Member::class);
    }

    public function sponsorMembers(): HasMany
    {
        return $this->hasMany(SponsorMember::class);
    }
}
