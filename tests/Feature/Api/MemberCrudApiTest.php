<?php

namespace Tests\Feature\Api;

use App\Models\District;
use App\Models\MapArea;
use App\Models\Member;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemberCrudApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_be_created_with_household_and_map_area(): void
    {
        $this->seed();

        $district = District::query()->where('code', '10区B2')->firstOrFail();
        $mapArea = MapArea::query()->where('svg_element_id', 'area10_B2')->firstOrFail();

        $response = $this->postJson('/api/members', [
            'district_id' => $district->id,
            'member_no' => 999,
            'name' => 'テスト 太郎',
            'name_kana' => 'テスト タロウ',
            'phone' => '090-0000-0000',
            'publication_status' => 'public',
            'membership_status' => 'active',
            'household' => [
                'district_id' => $district->id,
                'address_lot' => '999-1',
            ],
            'map_area_ids' => [$mapArea->id],
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.name', 'テスト 太郎')
            ->assertJsonPath('data.household.address_lot', '999-1')
            ->assertJsonPath('data.map_areas.0.svg_element_id', 'area10_B2');

        $this->assertDatabaseHas('members', [
            'name' => 'テスト 太郎',
            'member_no' => 999,
        ]);
        $this->assertDatabaseHas('map_area_member_links', [
            'map_area_id' => $mapArea->id,
            'member_id' => $response->json('data.id'),
            'link_type' => 'primary',
        ]);
    }

    public function test_member_can_be_updated_and_relinked_to_another_area(): void
    {
        $this->seed();

        $member = Member::query()->where('member_no', 1)->firstOrFail();
        $mapArea = MapArea::query()->where('svg_element_id', 'area10_B2')->firstOrFail();

        $this->patchJson("/api/members/{$member->id}", [
            'name' => '更新済み 会員',
            'phone' => '048-000-1111',
            'map_area_ids' => [$mapArea->id],
        ])
            ->assertOk()
            ->assertJsonPath('data.name', '更新済み 会員')
            ->assertJsonPath('data.phone', '048-000-1111')
            ->assertJsonPath('data.map_areas.0.svg_element_id', 'area10_B2');

        $this->assertDatabaseHas('members', [
            'id' => $member->id,
            'name' => '更新済み 会員',
            'phone' => '048-000-1111',
        ]);
        $this->assertDatabaseHas('map_area_member_links', [
            'map_area_id' => $mapArea->id,
            'member_id' => $member->id,
        ]);
    }

    public function test_member_can_be_soft_deleted(): void
    {
        $this->seed();

        $member = Member::query()->where('member_no', 1)->firstOrFail();

        $this->deleteJson("/api/members/{$member->id}")
            ->assertNoContent();

        $this->assertSoftDeleted('members', [
            'id' => $member->id,
        ]);
        $this->assertDatabaseMissing('map_area_member_links', [
            'member_id' => $member->id,
        ]);
    }
}
