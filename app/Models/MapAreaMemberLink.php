<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapAreaMemberLink extends Model
{
    protected $fillable = [
        'map_area_id',
        'member_id',
        'sponsor_member_id',
        'link_type',
    ];

    public function mapArea(): BelongsTo
    {
        return $this->belongsTo(MapArea::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function sponsorMember(): BelongsTo
    {
        return $this->belongsTo(SponsorMember::class);
    }
}
