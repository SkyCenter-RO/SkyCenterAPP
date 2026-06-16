<?php

namespace Tests\Feature\Marketing;

use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingCampaignResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->operator = User::factory()->create(['role' => 'operator', 'is_active' => true]);
    }

    public function test_admin_can_access_campaigns_index(): void
    {
        $this->actingAs($this->admin)
            ->get(MarketingCampaignResource::getUrl('index'))
            ->assertSuccessful();
    }

    public function test_operator_cannot_access_campaigns(): void
    {
        $this->actingAs($this->operator)
            ->get(MarketingCampaignResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_admin_can_access_create_campaign_page(): void
    {
        $this->actingAs($this->admin)
            ->get(MarketingCampaignResource::getUrl('create'))
            ->assertSuccessful();
    }
}
