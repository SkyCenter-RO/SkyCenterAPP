# Marketing Intelligence (Subproiect #6) — Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Adăugă un panel Marketing Intelligence în Filament cu 5 resurse (Campanii, Cheltuieli Ads, Recenzii, Calendar Conținut, Canale), acces admin-only, TDD complet.

**Architecture:** 5 migrări noi, 5 modele Eloquent, 5 resurse Filament cu structura folder-per-resource (ca restul proiectului). Totul rulează prin `docker compose exec -T app`. Worktree: `.worktrees/feature-marketing`, branch: `feature/marketing`.

**Tech Stack:** Laravel 12, Filament 5, PostgreSQL 16, PHPUnit, Docker Compose.

---

## Task 1: Branch & baseline

**Files:** *(niciun fișier nou)*

**Step 1: Verifică worktree-ul**
```bash
git -C "d:\Automation\SkyPark\App" worktree list
```
Expected: `.worktrees/feature-marketing` apare în listă pe branch `feature/marketing`.

**Step 2: Rulează testele baseline**
```bash
docker compose exec -T app php artisan test --stop-on-failure 2>&1
```
Expected: toate trec (149 tests / 418 assertions sau similar). Dacă fail → raportează înainte de a continua.

---

## Task 2: Migrare `marketing_campaigns`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000001_create_marketing_campaigns_table.php`

**Step 1: Generează migrarea**
```bash
docker compose exec -T app php artisan make:migration create_marketing_campaigns_table
```

**Step 2: Scrie migrarea**
```php
Schema::create('marketing_campaigns', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('platform', 64); // google|facebook|instagram|tiktok|bing|other
    $table->string('vertical', 32); // parcare|hotel|rent|bundle|general
    $table->string('status', 32)->default('active'); // active|paused|completed|draft
    $table->decimal('budget_eur', 10, 2)->nullable();
    $table->decimal('spend_eur', 10, 2)->nullable();
    $table->integer('conversions')->nullable();
    $table->decimal('cpc_eur', 8, 4)->nullable();
    $table->decimal('roas', 8, 2)->nullable();
    $table->date('period_month');
    $table->text('notes')->nullable();
    $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**Step 3: Rulează migrarea**
```bash
docker compose exec -T app php artisan migrate
```
Expected: `marketing_campaigns` tabel creat.

---

## Task 3: Migrare `marketing_ad_spend_logs`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000002_create_marketing_ad_spend_logs_table.php`

**Step 1: Generează migrarea**
```bash
docker compose exec -T app php artisan make:migration create_marketing_ad_spend_logs_table
```

**Step 2: Scrie migrarea**
```php
Schema::create('marketing_ad_spend_logs', function (Blueprint $table) {
    $table->id();
    $table->foreignId('campaign_id')->nullable()->constrained('marketing_campaigns')->nullOnDelete();
    $table->string('platform', 64);
    $table->string('vertical', 32)->nullable();
    $table->decimal('amount_eur', 10, 2);
    $table->date('spent_on')->index();
    $table->text('notes')->nullable();
    $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**Step 3: Rulează migrarea**
```bash
docker compose exec -T app php artisan migrate
```

---

## Task 4: Migrare `marketing_reviews`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000003_create_marketing_reviews_table.php`

**Step 1: Generează migrarea**
```bash
docker compose exec -T app php artisan make:migration create_marketing_reviews_table
```

**Step 2: Scrie migrarea**
```php
Schema::create('marketing_reviews', function (Blueprint $table) {
    $table->id();
    $table->string('platform', 64); // google|booking|facebook|tripadvisor|airbnb
    $table->string('vertical', 32)->nullable(); // hotel|parcare|rent|all
    $table->decimal('score', 3, 2);
    $table->integer('review_count')->nullable();
    $table->date('recorded_on')->index();
    $table->text('notes')->nullable();
    $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamp('created_at')->useCurrent();
});
```

**Step 3: Rulează migrarea**
```bash
docker compose exec -T app php artisan migrate
```

---

## Task 5: Migrare `marketing_content_calendar`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000004_create_marketing_content_calendar_table.php`

**Step 1: Generează migrarea**
```bash
docker compose exec -T app php artisan make:migration create_marketing_content_calendar_table
```

**Step 2: Scrie migrarea**
```php
Schema::create('marketing_content_calendar', function (Blueprint $table) {
    $table->id();
    $table->string('title');
    $table->string('platform', 64); // facebook|instagram|tiktok|all
    $table->string('vertical', 32)->nullable();
    $table->string('content_type', 64); // photo|reel|story|carousel|text
    $table->string('language', 8)->default('ro');
    $table->string('status', 32)->default('idea'); // idea|in_progress|ready|scheduled|published|cancelled
    $table->date('scheduled_at')->nullable();
    $table->date('published_at')->nullable();
    $table->text('copy_text')->nullable();
    $table->text('notes')->nullable();
    $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
    $table->timestamps();
});
```

**Step 3: Rulează migrarea**
```bash
docker compose exec -T app php artisan migrate
```

---

## Task 6: Migrare `marketing_channels`

**Files:**
- Create: `database/migrations/YYYY_MM_DD_000005_create_marketing_channels_table.php`

**Step 1: Generează migrarea**
```bash
docker compose exec -T app php artisan make:migration create_marketing_channels_table
```

**Step 2: Scrie migrarea**
```php
Schema::create('marketing_channels', function (Blueprint $table) {
    $table->id();
    $table->string('name', 128);
    $table->string('channel_type', 64); // ads|seo|social|listing|affiliate|email
    $table->string('status', 32)->default('setup_needed'); // active|setup_needed|paused|monitoring|blocked
    $table->text('url')->nullable();
    $table->string('account_id', 255)->nullable();
    $table->decimal('monthly_budget_eur', 10, 2)->nullable();
    $table->text('notes')->nullable();
    $table->date('last_reviewed_at')->nullable();
    $table->timestamps();
});
```

**Step 3: Rulează migrarea**
```bash
docker compose exec -T app php artisan migrate
```

---

## Task 7: Seed canale inițiale

**Files:**
- Create: `database/seeders/MarketingChannelSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`

**Step 1: Creează seeder-ul**
```php
<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MarketingChannelSeeder extends Seeder
{
    public function run(): void
    {
        $channels = [
            ['name' => 'Google Ads - PMax RO', 'channel_type' => 'ads', 'status' => 'active', 'monthly_budget_eur' => 150.00, 'notes' => '50% din bugetul total. Parcare principală.'],
            ['name' => 'Google Ads - PMax EN/IT/RU', 'channel_type' => 'ads', 'status' => 'active', 'monthly_budget_eur' => 0.00, 'notes' => 'Campanii internaționale - buget inclus în RO deocamdată.'],
            ['name' => 'Facebook / Instagram Ads', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 75.00, 'url' => 'https://www.facebook.com/SkyCenterIasi', 'notes' => '25% din buget. Prospecting + retargeting toate verticalele.'],
            ['name' => 'Google LSA / TikTok Ads', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 45.00, 'notes' => '15% din buget. Pilot Rent-a-car.'],
            ['name' => 'Google Hotel Ads (Sky Park Home)', 'channel_type' => 'ads', 'status' => 'setup_needed', 'monthly_budget_eur' => 30.00, 'notes' => '10% din buget. Bundle Park & Fly.'],
            ['name' => 'Google Business Profile', 'channel_type' => 'seo', 'status' => 'active', 'url' => 'https://business.google.com', 'notes' => 'GBP activ. Target: 100+ recenzii, scor > 4.8. QR review de implementat (M26).'],
            ['name' => 'Facebook Page', 'channel_type' => 'social', 'status' => 'active', 'url' => 'https://www.facebook.com/SkyCenterIasi', 'notes' => '35 recenzii, 100% recomandări. Grupuri diaspora.'],
            ['name' => 'Instagram', 'channel_type' => 'social', 'status' => 'monitoring', 'url' => 'https://www.instagram.com/skycenterromania', 'notes' => 'Activ. Reels Lo-Fi prioritar.'],
            ['name' => 'TikTok', 'channel_type' => 'social', 'status' => 'setup_needed', 'notes' => 'Neînceput. TikTok SEO, text pe ecran, UGC.'],
            ['name' => 'Booking.com', 'channel_type' => 'listing', 'status' => 'active', 'notes' => 'Hotel listat. Audit property/content score în așteptare (M28).'],
            ['name' => 'Airbnb', 'channel_type' => 'listing', 'status' => 'monitoring', 'notes' => 'iCal sync activ pentru cazare.'],
            ['name' => 'TripAdvisor', 'channel_type' => 'listing', 'status' => 'setup_needed', 'notes' => 'De revendicat (M34).'],
            ['name' => 'Apple Business Connect', 'channel_type' => 'seo', 'status' => 'blocked', 'notes' => 'Blocat - verificare necesară (M15).'],
            ['name' => 'Bing Places', 'channel_type' => 'seo', 'status' => 'monitoring', 'notes' => 'În lucru (M34).'],
            ['name' => 'ParkVia', 'channel_type' => 'affiliate', 'status' => 'setup_needed', 'notes' => 'Parteneriat WizzAir focus (M23).'],
            ['name' => 'Email Marketing (Listmonk)', 'channel_type' => 'email', 'status' => 'setup_needed', 'notes' => 'SPF/DKIM/DMARC + Listmonk (M31).'],
        ];

        foreach ($channels as $channel) {
            DB::table('marketing_channels')->insertOrIgnore(array_merge($channel, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }
}
```

**Step 2: Adaugă în DatabaseSeeder.php**
```php
$this->call(MarketingChannelSeeder::class);
```

**Step 3: Rulează seeder-ul**
```bash
docker compose exec -T app php artisan db:seed --class=MarketingChannelSeeder
```
Expected: 16 canale inserate.

**Step 4: Commit**
```bash
git add database/migrations/ database/seeders/MarketingChannelSeeder.php database/seeders/DatabaseSeeder.php
git commit -m "feat: add marketing intelligence migrations and channel seeder"
```

---

## Task 8: Modele Eloquent (5 modele)

**Files:**
- Create: `app/Models/MarketingCampaign.php`
- Create: `app/Models/MarketingAdSpendLog.php`
- Create: `app/Models/MarketingReview.php`
- Create: `app/Models/MarketingContentCalendar.php`
- Create: `app/Models/MarketingChannel.php`

**Step 1: Scrie testele modelelor**
Creează `tests/Feature/Marketing/MarketingModelsTest.php`:
```php
<?php
namespace Tests\Feature\Marketing;

use App\Models\MarketingCampaign;
use App\Models\MarketingAdSpendLog;
use App\Models\MarketingReview;
use App\Models\MarketingContentCalendar;
use App\Models\MarketingChannel;
use App\Models\User;
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
        $log = MarketingAdSpendLog::create([
            'platform' => 'google',
            'amount_eur' => 5.50,
            'spent_on' => today(),
        ]);

        $this->assertDatabaseHas('marketing_ad_spend_logs', ['amount_eur' => 5.50]);
    }

    public function test_can_create_review(): void
    {
        $review = MarketingReview::create([
            'platform' => 'google',
            'score' => 4.80,
            'review_count' => 120,
            'recorded_on' => today(),
        ]);

        $this->assertDatabaseHas('marketing_reviews', ['platform' => 'google', 'score' => 4.80]);
    }

    public function test_can_create_content_calendar_entry(): void
    {
        $entry = MarketingContentCalendar::create([
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
        $channel = MarketingChannel::create([
            'name' => 'Google Business Profile',
            'channel_type' => 'seo',
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('marketing_channels', ['name' => 'Google Business Profile']);
    }
}
```

**Step 2: Rulează testele (trebuie să eșueze — modelele nu există)**
```bash
docker compose exec -T app php artisan test tests/Feature/Marketing/MarketingModelsTest.php 2>&1
```
Expected: FAIL — class not found.

**Step 3: Scrie `MarketingCampaign.php`**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'name', 'platform', 'vertical', 'status',
        'budget_eur', 'spend_eur', 'conversions',
        'cpc_eur', 'roas', 'period_month', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'period_month' => 'date',
        'budget_eur' => 'decimal:2',
        'spend_eur' => 'decimal:2',
        'cpc_eur' => 'decimal:4',
        'roas' => 'decimal:2',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function spendLogs(): HasMany
    {
        return $this->hasMany(MarketingAdSpendLog::class, 'campaign_id');
    }
}
```

**Step 4: Scrie `MarketingAdSpendLog.php`**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAdSpendLog extends Model
{
    protected $fillable = [
        'campaign_id', 'platform', 'vertical',
        'amount_eur', 'spent_on', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'spent_on' => 'date',
        'amount_eur' => 'decimal:2',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
```

**Step 5: Scrie `MarketingReview.php`**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingReview extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'platform', 'vertical', 'score', 'review_count',
        'recorded_on', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'recorded_on' => 'date',
        'score' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
```

**Step 6: Scrie `MarketingContentCalendar.php`**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingContentCalendar extends Model
{
    protected $table = 'marketing_content_calendar';

    protected $fillable = [
        'title', 'platform', 'vertical', 'content_type', 'language',
        'status', 'scheduled_at', 'published_at', 'copy_text', 'notes', 'created_by_id',
    ];

    protected $casts = [
        'scheduled_at' => 'date',
        'published_at' => 'date',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
```

**Step 7: Scrie `MarketingChannel.php`**
```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingChannel extends Model
{
    protected $fillable = [
        'name', 'channel_type', 'status', 'url', 'account_id',
        'monthly_budget_eur', 'notes', 'last_reviewed_at',
    ];

    protected $casts = [
        'last_reviewed_at' => 'date',
        'monthly_budget_eur' => 'decimal:2',
    ];
}
```

**Step 8: Rulează testele — trebuie să treacă**
```bash
docker compose exec -T app php artisan test tests/Feature/Marketing/MarketingModelsTest.php 2>&1
```
Expected: 5 tests PASS.

**Step 9: Commit**
```bash
git add app/Models/Marketing*.php tests/Feature/Marketing/MarketingModelsTest.php
git commit -m "feat: add marketing Eloquent models with tests"
```

---

## Task 9: Resursă Filament — MarketingCampaigns

**Files:**
- Create: `app/Filament/Resources/MarketingCampaigns/MarketingCampaignResource.php`
- Create: `app/Filament/Resources/MarketingCampaigns/Pages/ListMarketingCampaigns.php`
- Create: `app/Filament/Resources/MarketingCampaigns/Pages/CreateMarketingCampaign.php`
- Create: `app/Filament/Resources/MarketingCampaigns/Pages/EditMarketingCampaign.php`
- Create: `app/Filament/Resources/MarketingCampaigns/Schemas/MarketingCampaignForm.php`
- Create: `app/Filament/Resources/MarketingCampaigns/Tables/MarketingCampaignsTable.php`
- Test: `tests/Feature/Marketing/MarketingCampaignResourceTest.php`

**Step 1: Scrie testul**
```php
<?php
namespace Tests\Feature\Marketing;

use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use App\Filament\Resources\MarketingCampaigns\Pages\CreateMarketingCampaign;
use App\Filament\Resources\MarketingCampaigns\Pages\ListMarketingCampaigns;
use App\Models\MarketingCampaign;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MarketingCampaignResourceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin    = User::factory()->create(['role' => 'admin']);
        $this->operator = User::factory()->create(['role' => 'operator']);
    }

    public function test_admin_can_list_campaigns(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(ListMarketingCampaigns::class)
            ->assertSuccessful();
    }

    public function test_operator_cannot_access_campaigns(): void
    {
        $this->actingAs($this->operator);
        $this->get(MarketingCampaignResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_admin_can_create_campaign(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(CreateMarketingCampaign::class)
            ->fillForm([
                'name'         => 'Test PMax',
                'platform'     => 'google',
                'vertical'     => 'parcare',
                'status'       => 'active',
                'budget_eur'   => 150.00,
                'period_month' => '2026-06-01',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('marketing_campaigns', ['name' => 'Test PMax']);
    }
}
```

**Step 2: Rulează testul (trebuie să eșueze)**
```bash
docker compose exec -T app php artisan test tests/Feature/Marketing/MarketingCampaignResourceTest.php 2>&1
```
Expected: FAIL — class not found.

**Step 3: Generează resursele Filament**

Creează directorul și fișierele cu structura standard a proiectului (copiind pattern-ul din `BudgetCategories`):

**`MarketingCampaignResource.php`:**
```php
<?php
namespace App\Filament\Resources\MarketingCampaigns;

use App\Models\MarketingCampaign;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MarketingCampaignResource extends Resource
{
    protected static ?string $model = MarketingCampaign::class;
    protected static \UnitEnum|string|null $navigationGroup = 'Marketing';
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-megaphone';
    protected static ?string $navigationLabel = 'Campanii';
    protected static ?string $modelLabel = 'campanie';
    protected static ?string $pluralModelLabel = 'campanii';
    protected static ?string $slug = 'marketing-campanii';
    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return $user instanceof \App\Models\User && $user->isAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return \App\Filament\Resources\MarketingCampaigns\Schemas\MarketingCampaignForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return \App\Filament\Resources\MarketingCampaigns\Tables\MarketingCampaignsTable::configure($table);
    }

    public static function getRelations(): array { return []; }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMarketingCampaigns::route('/'),
            'create' => Pages\CreateMarketingCampaign::route('/create'),
            'edit'   => Pages\EditMarketingCampaign::route('/{record}/edit'),
        ];
    }
}
```

**`Schemas/MarketingCampaignForm.php`:**
```php
<?php
namespace App\Filament\Resources\MarketingCampaigns\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class MarketingCampaignForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255)->columnSpanFull(),
            Select::make('platform')
                ->required()
                ->options(['google' => 'Google', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok', 'bing' => 'Bing', 'other' => 'Altul']),
            Select::make('vertical')
                ->required()
                ->options(['parcare' => 'Parcare', 'hotel' => 'Hotel', 'rent' => 'Rent-a-car', 'bundle' => 'Bundle', 'general' => 'General']),
            Select::make('status')
                ->required()
                ->default('active')
                ->options(['active' => 'Activ', 'paused' => 'Pauzat', 'completed' => 'Finalizat', 'draft' => 'Draft']),
            DatePicker::make('period_month')->required()->label('Luna (prima zi)'),
            TextInput::make('budget_eur')->numeric()->prefix('€')->label('Buget (EUR)'),
            TextInput::make('spend_eur')->numeric()->prefix('€')->label('Cheltuieli reale (EUR)'),
            TextInput::make('conversions')->numeric()->label('Conversii'),
            TextInput::make('cpc_eur')->numeric()->prefix('€')->label('CPC mediu (EUR)'),
            TextInput::make('roas')->numeric()->label('ROAS'),
            Textarea::make('notes')->columnSpanFull(),
        ]);
    }
}
```

**`Tables/MarketingCampaignsTable.php`:**
```php
<?php
namespace App\Filament\Resources\MarketingCampaigns\Tables;

use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteBulkAction;

class MarketingCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('platform')->badge()->sortable(),
                TextColumn::make('vertical')->badge()->sortable(),
                TextColumn::make('status')->badge()
                    ->color(fn (string $state): string => match($state) {
                        'active'    => 'success',
                        'paused'    => 'warning',
                        'completed' => 'gray',
                        'draft'     => 'info',
                        default     => 'gray',
                    }),
                TextColumn::make('period_month')->date('M Y')->sortable(),
                TextColumn::make('budget_eur')->money('EUR')->label('Buget'),
                TextColumn::make('spend_eur')->money('EUR')->label('Cheltuit'),
                TextColumn::make('roas')->label('ROAS')->suffix('x'),
                TextColumn::make('conversions')->label('Conversii'),
            ])
            ->filters([
                SelectFilter::make('platform')->options(['google' => 'Google', 'facebook' => 'Facebook', 'instagram' => 'Instagram', 'tiktok' => 'TikTok']),
                SelectFilter::make('vertical')->options(['parcare' => 'Parcare', 'hotel' => 'Hotel', 'rent' => 'Rent-a-car']),
                SelectFilter::make('status')->options(['active' => 'Activ', 'paused' => 'Pauzat', 'completed' => 'Finalizat']),
            ])
            ->actions([EditAction::make()])
            ->bulkActions([DeleteBulkAction::make()])
            ->defaultSort('period_month', 'desc');
    }
}
```

**Pages (List/Create/Edit) — pattern standard:**
```php
// ListMarketingCampaigns.php
<?php
namespace App\Filament\Resources\MarketingCampaigns\Pages;
use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
class ListMarketingCampaigns extends ListRecords {
    protected static string $resource = MarketingCampaignResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}

// CreateMarketingCampaign.php
<?php
namespace App\Filament\Resources\MarketingCampaigns\Pages;
use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use Filament\Resources\Pages\CreateRecord;
class CreateMarketingCampaign extends CreateRecord {
    protected static string $resource = MarketingCampaignResource::class;
}

// EditMarketingCampaign.php
<?php
namespace App\Filament\Resources\MarketingCampaigns\Pages;
use App\Filament\Resources\MarketingCampaigns\MarketingCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
class EditMarketingCampaign extends EditRecord {
    protected static string $resource = MarketingCampaignResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make()]; }
}
```

**Step 4: Rulează testele**
```bash
docker compose exec -T app php artisan test tests/Feature/Marketing/MarketingCampaignResourceTest.php 2>&1
```
Expected: 3 tests PASS.

**Step 5: Commit**
```bash
git add app/Filament/Resources/MarketingCampaigns/ tests/Feature/Marketing/MarketingCampaignResourceTest.php
git commit -m "feat: add MarketingCampaign Filament resource with admin-only access"
```

---

## Task 10: Resurse Filament — celelalte 4 (AdSpendLog, Review, ContentCalendar, Channel)

**Files:**
- Create: `app/Filament/Resources/MarketingAdSpendLogs/` (Resource + Schemas + Tables + Pages)
- Create: `app/Filament/Resources/MarketingReviews/` (Resource + Schemas + Tables + Pages)
- Create: `app/Filament/Resources/MarketingContentCalendar/` (Resource + Schemas + Tables + Pages)
- Create: `app/Filament/Resources/MarketingChannels/` (Resource + Schemas + Tables + Pages)
- Test: `tests/Feature/Marketing/MarketingResourcesAccessTest.php`

**Step 1: Scrie testul de acces**
```php
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
        $operator = User::factory()->create(['role' => 'operator']);
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
        $admin = User::factory()->create(['role' => 'admin']);
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
```

**Step 2: Creează cele 4 resurse**

Urmează exact același pattern ca MarketingCampaignResource (Task 9), adaptând:

**MarketingAdSpendLogResource:**
- navigationGroup: `'Marketing'`
- navigationIcon: `'heroicon-o-banknotes'`
- navigationLabel: `'Cheltuieli Ads'`
- slug: `'marketing-cheltuieli'`
- navigationSort: 2
- Form: `campaign_id` (Select din campanii), `platform`, `vertical`, `amount_eur`, `spent_on` (DatePicker), `notes`
- Table: `spent_on` (sortabil desc default), `platform`, `campaign.name`, `amount_eur` (money EUR)

**MarketingReviewResource:**
- navigationGroup: `'Marketing'`
- navigationIcon: `'heroicon-o-star'`
- navigationLabel: `'Recenzii'`
- slug: `'marketing-recenzii'`
- navigationSort: 3
- Form: `platform` (Select: google|booking|facebook|tripadvisor|airbnb), `vertical`, `score` (numeric 1-10), `review_count`, `recorded_on` (DatePicker), `notes`
- Table: `recorded_on` desc, `platform` badge, `vertical`, `score`, `review_count`

**MarketingContentCalendarResource:**
- navigationGroup: `'Marketing'`
- navigationIcon: `'heroicon-o-calendar-days'`
- navigationLabel: `'Calendar Conținut'`
- slug: `'marketing-calendar'`
- navigationSort: 4
- Form: `title`, `platform` (facebook|instagram|tiktok|all), `vertical`, `content_type` (photo|reel|story|carousel|text), `language`, `status`, `scheduled_at`, `published_at`, `copy_text` (Textarea), `notes`
- Table: `scheduled_at` desc, `title`, `platform` badge, `status` badge (culori: idea=gray, in_progress=warning, ready=info, scheduled=primary, published=success, cancelled=danger), `content_type`

**MarketingChannelResource:**
- navigationGroup: `'Marketing'`
- navigationIcon: `'heroicon-o-signal'`
- navigationLabel: `'Canale'`
- slug: `'marketing-canale'`
- navigationSort: 5
- Form: `name`, `channel_type` (ads|seo|social|listing|affiliate|email), `status` (active|setup_needed|paused|monitoring|blocked), `url`, `account_id`, `monthly_budget_eur`, `last_reviewed_at`, `notes`
- Table: `name`, `channel_type` badge, `status` badge (culori: active=success, setup_needed=warning, paused=gray, monitoring=info, blocked=danger), `monthly_budget_eur` money EUR, `last_reviewed_at`

**Step 3: Rulează testele**
```bash
docker compose exec -T app php artisan test tests/Feature/Marketing/ 2>&1
```
Expected: toate testele din folder PASS.

**Step 4: Commit**
```bash
git add app/Filament/Resources/MarketingAdSpendLogs/ \
        app/Filament/Resources/MarketingReviews/ \
        app/Filament/Resources/MarketingContentCalendar/ \
        app/Filament/Resources/MarketingChannels/ \
        tests/Feature/Marketing/MarketingResourcesAccessTest.php
git commit -m "feat: add remaining 4 marketing Filament resources with admin-only access"
```

---

## Task 11: Full test suite

**Step 1: Rulează toate testele**
```bash
docker compose exec -T app php artisan test 2>&1
```
Expected: toate trec (fără regresii).

**Step 2: Dacă trec — commit final**
```bash
git add -A
git commit -m "feat: subproject 6 marketing intelligence complete" --allow-empty
```

---

## Task 12: Merge în master

**Step 1: Push branch**
```bash
git push origin feature/marketing 2>&1
```

**Step 2: Merge în master**
```bash
git checkout master
git merge --no-ff feature/marketing -m "feat: subproject 6 - Marketing Intelligence panel"
git push origin master
```

**Step 3: Curăță worktree**
```bash
git worktree remove .worktrees/feature-marketing
git branch -d feature/marketing
```

**Step 4: Actualizează `docs/plans/task.md`**
Marchează Subproiect #6 ca `[x] done`.
