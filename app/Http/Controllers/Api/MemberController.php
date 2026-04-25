<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        $members = Member::query()
            ->with([
                'district:id,code,display_name',
                'household:id,address_lot,building_name,room_no',
                'areaLinks.mapArea:id,svg_element_id,display_name,district_id',
            ])
            ->when($keyword !== '', function (Builder $query) use ($keyword): void {
                $query->where(function (Builder $inner) use ($keyword): void {
                    $inner
                        ->where('name', 'like', "%{$keyword}%")
                        ->orWhere('name_kana', 'like', "%{$keyword}%")
                        ->orWhere('phone', 'like', "%{$keyword}%")
                        ->orWhere('note', 'like', "%{$keyword}%")
                        ->orWhereHas('district', function (Builder $districtQuery) use ($keyword): void {
                            $districtQuery->where('code', 'like', "%{$keyword}%");
                        })
                        ->orWhereHas('household', function (Builder $householdQuery) use ($keyword): void {
                            $householdQuery->where('address_lot', 'like', "%{$keyword}%");
                        });
                });
            })
            ->orderBy('member_no')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $members->map(fn (Member $member): array => $this->serializeMember($member))->values(),
            'meta' => [
                'limit' => $limit,
                'keyword' => $keyword,
            ],
        ]);
    }

    private function serializeMember(Member $member): array
    {
        return [
            'id' => $member->id,
            'member_no' => $member->member_no,
            'source_row_no' => $member->source_row_no,
            'name' => $member->name,
            'name_kana' => $member->name_kana,
            'phone' => $member->phone,
            'note' => $member->note,
            'publication_status' => $member->publication_status,
            'membership_status' => $member->membership_status,
            'district' => $member->district ? [
                'id' => $member->district->id,
                'code' => $member->district->code,
                'display_name' => $member->district->display_name,
            ] : null,
            'household' => $member->household ? [
                'id' => $member->household->id,
                'address_lot' => $member->household->address_lot,
                'building_name' => $member->household->building_name,
                'room_no' => $member->household->room_no,
            ] : null,
            'map_areas' => $member->areaLinks
                ->pluck('mapArea')
                ->filter()
                ->map(fn ($area): array => [
                    'id' => $area->id,
                    'svg_element_id' => $area->svg_element_id,
                    'display_name' => $area->display_name,
                ])
                ->values(),
        ];
    }
}
