<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\District;
use App\Models\MapArea;
use App\Models\Member;
use App\Models\SponsorMember;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function summary(): JsonResponse
    {
        return response()->json([
            'members' => Member::count(),
            'sponsor_members' => SponsorMember::count(),
            'districts' => District::count(),
            'map_areas' => MapArea::count(),
        ]);
    }
}
