<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['name' => 'Google Ads - PMax RO', 'channel_type' => 'ads', 'status' => 'active', 'monthly_budget_eur' => 150.00, 'url' => null, 'account_id' => null, 'notes' => '50% din bugetul total. Campanie principală parcare RO.'],
            ['name' => 'Google Ads - PMax EN/IT/RU', 'channel_type' => 'ads', 'status' => 'active', 'monthly_budget_eur' => 0.00, 'url' => null, 'account_id' => null, 'notes' => 'Campanii internaționale incluse în alocarea RO deocamdată.'],
            ['name' => 'Facebook / Instagram Ads', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 75.00, 'url' => 'https://www.facebook.com/SkyCenterIasi', 'account_id' => null, 'notes' => '25% din buget. Prospecting + retargeting toate verticalele.'],
            ['name' => 'Google LSA / TikTok Ads', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 45.00, 'url' => null, 'account_id' => null, 'notes' => '15% din buget. Pilot Rent-a-car LSA + TikTok influenceri.'],
            ['name' => 'Google Hotel Ads (Sky Park Home)', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 30.00, 'url' => null, 'account_id' => null, 'notes' => '10% din buget. Bundle Park & Fly €85. Necesită motor rezervări live.'],
            ['name' => 'Google Business Profile', 'channel_type' => 'seo', 'status' => 'active', 'monthly_budget_eur' => null, 'url' => 'https://business.google.com', 'account_id' => null, 'notes' => 'GBP activ. Target: 100+ recenzii, scor > 4.8. QR review de implementat (M26).'],
            ['name' => 'Facebook Page (Organic)', 'channel_type' => 'social', 'status' => 'active', 'monthly_budget_eur' => null, 'url' => 'https://www.facebook.com/SkyCenterIasi', 'account_id' => null, 'notes' => '35 recenzii organice, 100% recomandări. Grupuri diaspora.'],
            ['name' => 'Instagram (Organic)', 'channel_type' => 'social', 'status' => 'monitoring', 'monthly_budget_eur' => null, 'url' => 'https://www.instagram.com/skycenterromania', 'account_id' => null, 'notes' => 'Activ. Reels Lo-Fi prioritar. Auto-posting planificat Faza 2 (n8n).'],
            ['name' => 'TikTok (Organic)', 'channel_type' => 'social', 'status' => 'setup_needed', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'Neînceput. TikTok SEO, text pe ecran, UGC. Prioritate medie.'],
            ['name' => 'Booking.com', 'channel_type' => 'listing', 'status' => 'active', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'Hotel listat. Audit property/content score în așteptare (M28).'],
            ['name' => 'Airbnb', 'channel_type' => 'listing', 'status' => 'monitoring', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'iCal sync activ pentru cazare (delay 5-7h).'],
            ['name' => 'TripAdvisor', 'channel_type' => 'listing', 'status' => 'setup_needed', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'De revendicat (M34). Bing Places + TripAdvisor claim/check NAP.'],
            ['name' => 'Apple Business Connect', 'channel_type' => 'seo', 'status' => 'blocked', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'Blocat - verificare Apple necesară (M15).'],
            ['name' => 'Bing Places', 'channel_type' => 'seo', 'status' => 'monitoring', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'În lucru (M34). Activare Microsoft Ads când buget > €500.'],
            ['name' => 'ParkVia (Afiliat)', 'channel_type' => 'affiliate', 'status' => 'setup_needed', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'Parteneriat WizzAir focus. Parcare aeroport internațional (M23).'],
            ['name' => 'Email Marketing (Listmonk)', 'channel_type' => 'email', 'status' => 'setup_needed', 'monthly_budget_eur' => null, 'url' => null, 'account_id' => null, 'notes' => 'SPF/DKIM/DMARC + Listmonk de configurat (M31).'],
        ];

        foreach ($channels as $channel) {
            DB::table('marketing_channels')->insertOrIgnore(array_merge($channel, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
