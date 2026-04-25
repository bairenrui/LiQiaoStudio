<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class InitialMapDataSeeder extends Seeder
{
    /**
     * Seed the application's initial map and member data.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $adminId = $this->seedAdminUser();
            $districtIds = $this->seedDistricts();
            $mapVersionId = $this->seedMapVersion($adminId);
            $mapAreaIds = $this->seedMapAreas($mapVersionId, $districtIds);

            $this->seedMapLayers($mapVersionId);

            $memberIdsByRow = $this->seedMembers($districtIds, $adminId);
            $sponsorIdsByRow = $this->seedSponsorMembers($districtIds, $adminId);

            $this->seedAreaLinks(
                $mapAreaIds,
                $memberIdsByRow,
                $sponsorIdsByRow,
            );
        });
    }

    private function seedAdminUser(): int
    {
        DB::table('users')->updateOrInsert(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'is_active' => true,
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('users')->where('email', 'admin@example.com')->value('id');
    }

    /**
     * @return array<string, int>
     */
    private function seedDistricts(): array
    {
        foreach ($this->readJson('districts.json') as $district) {
            DB::table('districts')->updateOrInsert(
                ['code' => $district['code']],
                [
                    'district_no' => $district['district_no'],
                    'block_code' => $district['block_code'] ?: null,
                    'display_name' => $district['display_name'],
                    'sort_order' => $district['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        return DB::table('districts')->pluck('id', 'code')->map(fn ($id) => (int) $id)->all();
    }

    private function seedMapVersion(int $adminId): int
    {
        DB::table('map_versions')->updateOrInsert(
            ['name' => '大成町二丁目地図'],
            [
                'svg_path' => '/assets/map.svg',
                'view_box' => '0 0 4656.46 4293.15',
                'is_active' => true,
                'created_by' => $adminId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        return (int) DB::table('map_versions')->where('name', '大成町二丁目地図')->value('id');
    }

    /**
     * @param array<string, int> $districtIds
     * @return array<string, int>
     */
    private function seedMapAreas(int $mapVersionId, array $districtIds): array
    {
        foreach ($this->readJson('map_areas.json') as $area) {
            DB::table('map_areas')->updateOrInsert(
                [
                    'map_version_id' => $mapVersionId,
                    'svg_element_id' => $area['svg_element_id'],
                ],
                [
                    'district_id' => $districtIds[$area['district_code']] ?? null,
                    'area_type' => $area['area_type'],
                    'display_name' => $area['display_name'],
                    'default_fill_color' => '#ffffff',
                    'highlight_fill_color' => '#a8d0ff',
                    'is_clickable' => true,
                    'has_source_range' => (bool) $area['has_source_range'],
                    'sort_order' => $area['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        return DB::table('map_areas')
            ->where('map_version_id', $mapVersionId)
            ->pluck('id', 'svg_element_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function seedMapLayers(int $mapVersionId): void
    {
        foreach ($this->readJson('map_layers.json') as $layer) {
            DB::table('map_layers')->updateOrInsert(
                [
                    'map_version_id' => $mapVersionId,
                    'key_name' => $layer['key_name'],
                ],
                [
                    'svg_group_id' => $layer['svg_group_id'],
                    'display_name' => $layer['display_name'],
                    'is_default_visible' => (bool) $layer['is_default_visible'],
                    'sort_order' => $layer['sort_order'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }
    }

    /**
     * @param array<string, int> $districtIds
     * @return array<int, int>
     */
    private function seedMembers(array $districtIds, int $adminId): array
    {
        $memberIdsByRow = [];
        $householdIds = [];

        foreach ($this->readJson('members.json') as $member) {
            $districtId = $districtIds[$member['district_code']] ?? null;
            $householdKey = implode('|', [$districtId ?: 'none', $member['address_lot']]);

            if (!isset($householdIds[$householdKey])) {
                DB::table('households')->updateOrInsert(
                    [
                        'district_id' => $districtId,
                        'address_lot' => $member['address_lot'],
                        'building_name' => null,
                        'room_no' => null,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                $householdIds[$householdKey] = (int) DB::table('households')
                    ->where('district_id', $districtId)
                    ->where('address_lot', $member['address_lot'])
                    ->whereNull('building_name')
                    ->whereNull('room_no')
                    ->value('id');
            }

            DB::table('members')->updateOrInsert(
                ['source_row_no' => $member['source_row_no']],
                [
                    'household_id' => $householdIds[$householdKey],
                    'district_id' => $districtId,
                    'member_no' => $member['member_no'],
                    'name' => $member['name'] !== '' ? $member['name'] : '(氏名未入力)',
                    'name_kana' => $member['name_kana'] ?: null,
                    'phone' => $member['phone'] ?: null,
                    'note' => $member['note'] ?: null,
                    'publication_status' => $this->publicationStatus($member),
                    'membership_status' => 'active',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $memberIdsByRow[(int) $member['source_row_no']] = (int) DB::table('members')
                ->where('source_row_no', $member['source_row_no'])
                ->value('id');
        }

        return $memberIdsByRow;
    }

    /**
     * @param array<string, int> $districtIds
     * @return array<int, int>
     */
    private function seedSponsorMembers(array $districtIds, int $adminId): array
    {
        $sponsorIdsByRow = [];

        foreach ($this->readJson('sponsor_members.json') as $sponsor) {
            DB::table('sponsor_members')->updateOrInsert(
                ['source_row_no' => $sponsor['source_row_no']],
                [
                    'district_id' => $districtIds[$sponsor['district_code']] ?? null,
                    'sponsor_no' => $sponsor['sponsor_no'],
                    'address_lot' => $sponsor['address_lot'] ?: null,
                    'company_name' => $sponsor['company_name'],
                    'contact_name' => $sponsor['contact_name'] ?: null,
                    'phone' => $sponsor['phone'] ?: null,
                    'business_description' => $sponsor['business_description'] ?: null,
                    'membership_status' => 'active',
                    'created_by' => $adminId,
                    'updated_by' => $adminId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $sponsorIdsByRow[(int) $sponsor['source_row_no']] = (int) DB::table('sponsor_members')
                ->where('source_row_no', $sponsor['source_row_no'])
                ->value('id');
        }

        return $sponsorIdsByRow;
    }

    /**
     * @param array<string, int> $mapAreaIds
     * @param array<int, int> $memberIdsByRow
     * @param array<int, int> $sponsorIdsByRow
     */
    private function seedAreaLinks(array $mapAreaIds, array $memberIdsByRow, array $sponsorIdsByRow): void
    {
        foreach ($this->readJson('member_area_ranges.json') as $range) {
            $mapAreaId = $mapAreaIds[$range['svg_element_id']] ?? null;
            if (!$mapAreaId) {
                continue;
            }

            for ($rowNo = $range['start_row']; $rowNo <= $range['end_row']; $rowNo++) {
                if (!isset($memberIdsByRow[$rowNo])) {
                    continue;
                }

                DB::table('map_area_member_links')->updateOrInsert(
                    [
                        'map_area_id' => $mapAreaId,
                        'member_id' => $memberIdsByRow[$rowNo],
                        'sponsor_member_id' => null,
                    ],
                    [
                        'link_type' => 'source_range',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }

        foreach ($this->readJson('sponsor_area_ranges.json') as $range) {
            $mapAreaId = $mapAreaIds[$range['svg_element_id']] ?? null;
            if (!$mapAreaId) {
                continue;
            }

            for ($rowNo = $range['start_row']; $rowNo <= $range['end_row']; $rowNo++) {
                if (!isset($sponsorIdsByRow[$rowNo])) {
                    continue;
                }

                DB::table('map_area_member_links')->updateOrInsert(
                    [
                        'map_area_id' => $mapAreaId,
                        'member_id' => null,
                        'sponsor_member_id' => $sponsorIdsByRow[$rowNo],
                    ],
                    [
                        'link_type' => 'source_range',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }
        }
    }

    /**
     * @return array<int, mixed>
     */
    private function readJson(string $filename): array
    {
        $path = base_path("data/{$filename}");
        $json = file_get_contents($path);

        if ($json === false) {
            throw new \RuntimeException("Cannot read {$path}");
        }

        return json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $member
     */
    private function publicationStatus(array $member): string
    {
        $text = implode(' ', [
            $member['phone'] ?? '',
            $member['note'] ?? '',
        ]);

        return str_contains($text, '不掲載') ? 'unlisted' : 'public';
    }
}
