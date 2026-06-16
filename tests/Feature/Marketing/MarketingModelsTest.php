<?php

namespace Tests\Feature\Marketing;

use App\Models\MarketingAdSpendLog;
use App\Models\MarketingCampaign;
use App\Models\MarketingChannel;
use App\Models\MarketingContentCalendar;
use App\Models\MarketingReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_campaign(): void
    {
        $campaign = MarketingCampaign::create([
            'name' => 'PMax - RO Parcare',
            'platform' => 'google',
            'vertical' => 'parcare',
            'status' => 'active',
            'budget_eur' => 150.00,
            'period_month' => '2026-06-01',
        ]);

        $this->assertDatabaseHas('marketing_campaigns', ['name' => 'PMax - RO Parcare']);
    }

    public function test_can_create_ad_spend_log(): void
    {
        $campaign = MarketingCampaign::create([
            'name' => 'Test Campaign',
            'platform' => 'google',
            'vertical' => 'parcare',
            'status' => 'active',
            'period_month' => '2026-06-01',
        ]);

        MarketingAdSpendLog::create([
            'campaign_id' => $campaign->id,
            'platform' => 'google',
            'amount_eur' => 5.50,
            'spent_on' => today(),
        ]);

        $this->assertDatabaseHas('marketing_ad_spend_logs', ['amount_eur' => 5.50]);
        $this->assertCount(1, $campaign->spendLogs);
    }

    public function test_can_create_review(): void
    {
        MarketingReview::create([
            'platform' => 'google',
            'score' => 4.80,
            'review_count' => 120,
            'recorded_on' => today(),
        ]);

        $this->assertDatabaseHas('marketing_reviews', ['platform' => 'google', 'score' => 4.80]);
    }

    public function test_can_create_content_calendar_entry(): void
    {
        MarketingContentCalendar::create([
            'title' => 'Reel - Parcare Securizată',
            'platform' => 'instagram',
            'content_type' => 'reel',
            'language' => 'ro',
            'status' => 'idea',
        ]);

        $this->assertDatabaseHas('marketing_content_calendar', ['title' => 'Reel - Parcare Securizată']);
    }

    public function test_can_create_channel(): void
    {
        MarketingChannel::create([
            'name' => 'Google Business Profile',
            'channel_type' => 'seo',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('marketing_channels', ['name' => 'Google Business Profile']);
    }
}
