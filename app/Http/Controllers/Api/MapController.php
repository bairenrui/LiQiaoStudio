<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MapArea;
use App\Models\MapVersion;
use Illuminate\Http\JsonResponse;

class MapController extends Controller
{
    public function show(): JsonResponse
    {
        $mapVersion = $this->activeMapVersion();
        $mapVersion->load([
            'layers' => fn ($query) => $query->orderBy('sort_order'),
            'areas.district:id,code,display_name',
        ]);

        return response()->json([
            'map' => [
                'id' => $mapVersion->id,
                'name' => $mapVersion->name,
                'svg_path' => $mapVersion->svg_path,
                'view_box' => $mapVersion->view_box,
            ],
            'layers' => $mapVersion->layers->map(fn ($layer): array => [
                'id' => $layer->id,
                'key_name' => $layer->key_name,
                'svg_group_id' => $layer->svg_group_id,
                'display_name' => $layer->display_name,
                'is_default_visible' => $layer->is_default_visible,
                'sort_order' => $layer->sort_order,
            ])->values(),
            'areas' => $mapVersion->areas
                ->sortBy('sort_order')
                ->map(fn (MapArea $area): array => $this->serializeArea($area))
                ->values(),
        ]);
    }

    public function area(string $svgElementId): JsonResponse
    {
        $mapVersion = $this->activeMapVersion();

        $area = MapArea::query()
            ->where('map_version_id', $mapVersion->id)
            ->where('svg_element_id', $svgElementId)
            ->with([
                'district:id,code,display_name',
                'memberLinks.member.district:id,code,display_name',
                'memberLinks.member.household:id,address_lot,building_name,room_no',
                'memberLinks.sponsorMember.district:id,code,display_name',
            ])
            ->firstOrFail();

        $members = $area->memberLinks
            ->pluck('member')
            ->filter()
            ->sortBy('member_no')
            ->map(fn ($member): array => [
                'id' => $member->id,
                'member_no' => $member->member_no,
                'name' => $member->name,
                'name_kana' => $member->name_kana,
                'phone' => $member->phone,
                'note' => $member->note,
                'district_code' => $member->district?->code,
                'address_lot' => $member->household?->address_lot,
                'publication_status' => $member->publication_status,
            ])
            ->values();

        $sponsorMembers = $area->memberLinks
            ->pluck('sponsorMember')
            ->filter()
            ->sortBy('sponsor_no')
            ->map(fn ($sponsor): array => [
                'id' => $sponsor->id,
                'sponsor_no' => $sponsor->sponsor_no,
                'company_name' => $sponsor->company_name,
                'contact_name' => $sponsor->contact_name,
                'phone' => $sponsor->phone,
                'business_description' => $sponsor->business_description,
                'district_code' => $sponsor->district?->code,
                'address_lot' => $sponsor->address_lot,
            ])
            ->values();

        return response()->json([
            'area' => $this->serializeArea($area),
            'members' => $members,
            'sponsor_members' => $sponsorMembers,
        ]);
    }

    private function activeMapVersion(): MapVersion
    {
        return MapVersion::query()
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function serializeArea(MapArea $area): array
    {
        return [
            'id' => $area->id,
            'svg_element_id' => $area->svg_element_id,
            'area_type' => $area->area_type,
            'display_name' => $area->display_name,
            'default_fill_color' => $area->default_fill_color,
            'highlight_fill_color' => $area->highlight_fill_color,
            'is_clickable' => $area->is_clickable,
            'has_source_range' => $area->has_source_range,
            'sort_order' => $area->sort_order,
            'district' => $area->district ? [
                'id' => $area->district->id,
                'code' => $area->district->code,
                'display_name' => $area->district->display_name,
            ] : null,
        ];
    }
}
