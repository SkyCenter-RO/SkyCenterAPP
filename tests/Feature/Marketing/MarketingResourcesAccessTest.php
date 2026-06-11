<?php

namespace Tests\Feature\Marketing;

use App\Filament\Resources\MarketingAdSpendLogs\MarketingAdSpendLogResource;
use App\Filament\Resources\MarketingReviews\MarketingReviewResource;
use App\Filament\Resources\MarketingContentCalendar\MarketingContentCalendarResource;
use App\Filament\Resources\MarketingChannels\MarketingChannelResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingResourcesAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_cannot_access_any_marketing_resource(): void
    {
        $operator = User::factory()->create(['role' => 'operator', 'is_active' => true]);
        $this->actingAs($operator);

        $resources = [
            MarketingAdSpendLogResource::class,
            MarketingReviewResource::class,
            MarketingContentCalendarResource::class,
            MarketingChannelResource::class,
        ];

        foreach ($resources as $resource) {
            $this->get($resource::getUrl('index'))
                ->assertForbidden();
        }
    }

    public function test_admin_can_access_all_marketing_resources(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin);

        $resources = [
            MarketingAdSpendLogResource::class,
            MarketingReviewResource::class,
            MarketingContentCalendarResource::class,
            MarketingChannelResource::class,
        ];

        foreach ($resources as $resource) {
            $this->get($resource::getUrl('index'))
                ->assertSuccessful();
        }
    }
}
