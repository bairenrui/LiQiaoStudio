<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class SponsorMember extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'district_id',
        'sponsor_no',
        'address_lot',
        'company_name',
        'contact_name',
        'phone',
        'business_description',
        'note',
        'membership_status',
        'source_row_no',
        'created_by',
        'updated_by',
    ];

    public function district(): BelongsTo
    {
        return $this->belongsTo(District::class);
    }

    public function areaLinks(): HasMany
    {
        return $this->hasMany(MapAreaMemberLink::class);
    }
}
