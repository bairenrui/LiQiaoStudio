<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Household;
use App\Models\MapAreaMemberLink;
use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class MemberController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $keyword = trim((string) $request->query('keyword', ''));
        $limit = min(max((int) $request->query('limit', 50), 1), 200);

        $members = Member::query()
            ->with($this->memberRelations())
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

    public function show(Member $member): JsonResponse
    {
        $member->load($this->memberRelations());

        return response()->json([
            'data' => $this->serializeMember($member),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validatePayload($request);

        $member = DB::transaction(function () use ($validated): Member {
            $household = $this->resolveHousehold($validated);

            $member = Member::create([
                'household_id' => $household?->id,
                'district_id' => $validated['district_id'] ?? $household?->district_id,
                'member_no' => $validated['member_no'] ?? null,
                'name' => $validated['name'],
                'name_kana' => $validated['name_kana'] ?? null,
                'phone' => $validated['phone'] ?? null,
                'note' => $validated['note'] ?? null,
                'publication_status' => $validated['publication_status'] ?? 'public',
                'membership_status' => $validated['membership_status'] ?? 'active',
            ]);

            $this->syncMapAreas($member, $validated['map_area_ids'] ?? []);

            return $member;
        });

        $member->load($this->memberRelations());

        return response()->json([
            'data' => $this->serializeMember($member),
        ], 201);
    }

    public function update(Request $request, Member $member): JsonResponse
    {
        $validated = $this->validatePayload($request, true);

        DB::transaction(function () use ($member, $validated): void {
            $household = array_key_exists('household', $validated)
                ? $this->resolveHousehold($validated)
                : $member->household;

            $member->fill([
                'household_id' => array_key_exists('household', $validated) ? $household?->id : $member->household_id,
                'district_id' => $validated['district_id'] ?? $household?->district_id ?? $member->district_id,
                'member_no' => $validated['member_no'] ?? $member->member_no,
                'name' => $validated['name'] ?? $member->name,
                'name_kana' => $validated['name_kana'] ?? $member->name_kana,
                'phone' => $validated['phone'] ?? $member->phone,
                'note' => $validated['note'] ?? $member->note,
                'publication_status' => $validated['publication_status'] ?? $member->publication_status,
                'membership_status' => $validated['membership_status'] ?? $member->membership_status,
            ]);
            $member->save();

            if (array_key_exists('map_area_ids', $validated)) {
                $this->syncMapAreas($member, $validated['map_area_ids']);
            }
        });

        $member->load($this->memberRelations());

        return response()->json([
            'data' => $this->serializeMember($member),
        ]);
    }

    public function destroy(Member $member): JsonResponse
    {
        DB::transaction(function () use ($member): void {
            $member->areaLinks()->delete();
            $member->delete();
        });

        return response()->json(status: 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'member_no' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'name' => [$required, 'string', 'max:100'],
            'name_kana' => ['sometimes', 'nullable', 'string', 'max:100'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:50'],
            'note' => ['sometimes', 'nullable', 'string'],
            'publication_status' => ['sometimes', Rule::in(['public', 'unlisted'])],
            'membership_status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'household' => ['sometimes', 'nullable', 'array'],
            'household.district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'household.address_lot' => ['required_with:household', 'string', 'max:50'],
            'household.building_name' => ['sometimes', 'nullable', 'string', 'max:100'],
            'household.room_no' => ['sometimes', 'nullable', 'string', 'max:50'],
            'household.note' => ['sometimes', 'nullable', 'string'],
            'map_area_ids' => ['sometimes', 'array'],
            'map_area_ids.*' => ['integer', 'exists:map_areas,id'],
        ]);
    }

    private function resolveHousehold(array $validated): ?Household
    {
        if (!array_key_exists('household', $validated) || $validated['household'] === null) {
            return null;
        }

        $household = $validated['household'];
        $districtId = $household['district_id'] ?? $validated['district_id'] ?? null;

        return Household::firstOrCreate(
            [
                'district_id' => $districtId,
                'address_lot' => $household['address_lot'],
                'building_name' => $household['building_name'] ?? null,
                'room_no' => $household['room_no'] ?? null,
            ],
            [
                'note' => $household['note'] ?? null,
            ],
        );
    }

    /**
     * @param array<int, int> $mapAreaIds
     */
    private function syncMapAreas(Member $member, array $mapAreaIds): void
    {
        $member->areaLinks()->delete();

        foreach (array_values(array_unique($mapAreaIds)) as $mapAreaId) {
            MapAreaMemberLink::create([
                'map_area_id' => $mapAreaId,
                'member_id' => $member->id,
                'sponsor_member_id' => null,
                'link_type' => 'primary',
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function memberRelations(): array
    {
        return [
            'district:id,code,display_name',
            'household:id,district_id,address_lot,building_name,room_no,note',
            'areaLinks.mapArea:id,svg_element_id,display_name,district_id',
        ];
    }

    private function serializeMember(Member $member): array
    {
        return [
            'id' => $member->id,
            'household_id' => $member->household_id,
            'district_id' => $member->district_id,
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
