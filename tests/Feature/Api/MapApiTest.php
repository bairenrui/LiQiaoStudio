<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MapApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_summary_returns_seeded_counts(): void
    {
        $this->seed();

        $this->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJson([
                'members' => 631,
                'sponsor_members' => 20,
                'districts' => 68,
                'map_areas' => 65,
            ]);
    }

    public function test_member_search_returns_map_area(): void
    {
        $this->seed();

        $keyword = urlencode('佐藤');

        $this->getJson("/api/members?keyword={$keyword}&limit=1")
            ->assertOk()
            ->assertJsonPath('data.0.map_areas.0.svg_element_id', 'area03_B2');
    }

    public function test_map_area_detail_returns_members(): void
    {
        $this->seed();

        $this->getJson('/api/map/areas/area10_B2')
            ->assertOk()
            ->assertJsonPath('area.svg_element_id', 'area10_B2')
            ->assertJsonCount(10, 'members')
            ->assertJsonCount(0, 'sponsor_members');
    }
}
