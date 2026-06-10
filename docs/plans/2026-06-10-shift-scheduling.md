# Shift Scheduling Implementation Plan

> **For Antigravity:** REQUIRED WORKFLOW: Use `.agent/workflows/execute-plan.md` to execute this plan in single-flow mode.

**Goal:** Implement Subproject #5: Shift Scheduling (Orar / Ture) by allowing admins to upload a PDF schedule from the "Ordinea de zi" page, parsing it on the server, storing it in the database, and displaying the active operator for both day and night shifts on the dashboard.

**Architecture:** Use the native PHP package `smalot/pdfparser` inside a Laravel Action class to parse the PDF text. Map parsed operator names to seeded operator users in the database, store them in a normalized `work_shifts` table with unique constraint on `[date, shift_type]`, and display the schedule in a card section on the Filament `Ordinea de zi` page.

**Tech Stack:** PHP, Laravel, Livewire, Filament, PDF Parsing, PostgreSQL.

---

### Task 1: Composer & Seeder Setup

**Files:**
- Modify: [composer.json](file:///d:/Automation/SkyPark/App/composer.json)
- Create: [OperatorUserSeeder.php](file:///d:/Automation/SkyPark/App/database/seeders/OperatorUserSeeder.php)
- Modify: [DatabaseSeeder.php](file:///d:/Automation/SkyPark/App/database/seeders/DatabaseSeeder.php)
- Create: [OperatorUserSeederTest.php](file:///d:/Automation/SkyPark/App/tests/Feature/Seeder/OperatorUserSeederTest.php)

**Step 1: Write the failing test**
Create `tests/Feature/Seeder/OperatorUserSeederTest.php` with:
```php
<?php

namespace Tests\Feature\Seeder;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OperatorUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_operator_users(): void
    {
        $this->seed();

        $operators = ['Bratan', 'Bogdan', 'Matei', 'Catalin'];

        foreach ($operators as $name) {
            $user = User::query()->where('name', $name)->first();
            $this->assertNotNull($user);
            $this->assertEquals(User::ROLE_OPERATOR, $user->role);
            $this->assertTrue($user->is_active);
        }
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec -T app php artisan test --filter=OperatorUserSeederTest`
Expected: FAIL (class/database seeder doesn't seed them)

**Step 3: Write minimal implementation**
1. Add `smalot/pdfparser` to `composer.json` and install:
   Run: `docker compose exec -T app composer require smalot/pdfparser`
2. Create `database/seeders/OperatorUserSeeder.php`:
```php
<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class OperatorUserSeeder extends Seeder
{
    public function run(): void
    {
        $operators = [
            'Bratan' => 'bratan@skycenter.local',
            'Bogdan' => 'bogdan@skycenter.local',
            'Matei' => 'matei@skycenter.local',
            'Catalin' => 'catalin@skycenter.local',
        ];

        foreach ($operators as $name => $email) {
            $user = User::query()->firstOrNew(['email' => $email]);
            $user->name = $name;
            $user->role = User::ROLE_OPERATOR;
            $user->is_active = true;
            if (! $user->exists) {
                $user->password = Hash::make('parola-operator');
            }
            $user->save();
        }
    }
}
```
3. Call it in `database/seeders/DatabaseSeeder.php`:
```diff
         $this->call([
             AdminUserSeeder::class,
             ParkingReferenceSeeder::class,
             LodgingReferenceSeeder::class,
             MessageTemplateSeeder::class,
             BudgetCategorySeeder::class,
+            OperatorUserSeeder::class,
         ]);
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec -T app php artisan test --filter=OperatorUserSeederTest`
Expected: PASS

**Step 5: Commit**
```bash
git add composer.json composer.lock database/seeders/OperatorUserSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/Seeder/OperatorUserSeederTest.php
git commit -m "feat: add operator user seeder and pdf parser dependency"
```

---

### Task 2: Database Migration & Model

**Files:**
- Create: `database/migrations/2026_06_10_220000_create_work_shifts_table.php`
- Create: [WorkShift.php](file:///d:/Automation/SkyPark/App/app/Models/WorkShift.php)
- Modify: [CommonSchemaTest.php](file:///d:/Automation/SkyPark/App/tests/Feature/Schema/CommonSchemaTest.php)

**Step 1: Write the failing test**
Modify `tests/Feature/Schema/CommonSchemaTest.php` by adding checking for `work_shifts` table structure:
```php
    public function test_work_shifts_table_schema(): void
    {
        $this->assertTrue(Schema::hasTable('work_shifts'));
        $this->assertTrue(Schema::hasColumns('work_shifts', [
            'id', 'date', 'shift_type', 'user_id', 'raw_employee_name', 'created_at', 'updated_at'
        ]));
    }
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec -T app php artisan test --filter=CommonSchemaTest`
Expected: FAIL (table doesn't exist)

**Step 3: Write minimal implementation**
1. Generate migration:
   Run: `docker compose exec -T app php artisan make:migration create_work_shifts_table`
2. Update the generated migration file:
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function run(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('shift_type'); // 'zi' or 'noapte'
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('raw_employee_name')->nullable();
            $table->timestamps();

            $table->unique(['date', 'shift_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
```
3. Create model `app/Models/WorkShift.php`:
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkShift extends Model
{
    protected $fillable = [
        'date',
        'shift_type',
        'user_id',
        'raw_employee_name',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec -T app php artisan test --filter=CommonSchemaTest`
Expected: PASS

**Step 5: Commit**
```bash
git add database/migrations/*_create_work_shifts_table.php app/Models/WorkShift.php tests/Feature/Schema/CommonSchemaTest.php
git commit -m "feat: create work_shifts migration and model"
```

---

### Task 3: ParseSchedulePdfAction Class

**Files:**
- Create: [ParseSchedulePdfAction.php](file:///d:/Automation/SkyPark/App/app/Actions/Scheduling/ParseSchedulePdfAction.php)
- Create: [ParseSchedulePdfActionTest.php](file:///d:/Automation/SkyPark/App/tests/Feature/Scheduling/ParseSchedulePdfActionTest.php)

**Step 1: Write the failing test**
Create `tests/Feature/Scheduling/ParseSchedulePdfActionTest.php`:
```php
<?php

namespace Tests\Feature\Scheduling;

use App\Actions\Scheduling\ParseSchedulePdfAction;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ParseSchedulePdfActionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_parses_valid_pdf_schedule_correctly(): void
    {
        $filePath = base_path('MyFiles/Program_Iunie_2026_SkyCenter.pdf');
        
        $action = new ParseSchedulePdfAction();
        $result = $action->execute($filePath);

        $this->assertEquals('Iunie', $result['month_name']);
        $this->assertEquals(2026, $result['year']);
        $this->assertEquals(30, $result['imported_days']);

        // Assert that the first day has Bratan on day shift and Matei on night shift
        $day1Zi = WorkShift::query()->where('date', '2026-06-01')->where('shift_type', 'zi')->first();
        $day1Noapte = WorkShift::query()->where('date', '2026-06-01')->where('shift_type', 'noapte')->first();

        $this->assertNotNull($day1Zi);
        $this->assertEquals('Bratan', $day1Zi->user->name);

        $this->assertNotNull($day1Noapte);
        $this->assertEquals('Matei', $day1Noapte->user->name);
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec -T app php artisan test --filter=ParseSchedulePdfActionTest`
Expected: FAIL (class doesn't exist)

**Step 3: Write minimal implementation**
Create `app/Actions/Scheduling/ParseSchedulePdfAction.php`:
```php
<?php

namespace App\Actions\Scheduling;

use App\Models\User;
use App\Models\WorkShift;
use Smalot\PdfParser\Parser;

class ParseSchedulePdfAction
{
    private const MONTHS_MAP = [
        'ianuarie' => 1,
        'februarie' => 2,
        'martie' => 3,
        'aprilie' => 4,
        'mai' => 5,
        'iunie' => 6,
        'iulie' => 7,
        'august' => 8,
        'septembrie' => 9,
        'octombrie' => 10,
        'noiembrie' => 11,
        'decembrie' => 12,
    ];

    public function execute(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Fișierul nu există la calea: {$filePath}");
        }

        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Extract month & year from header
        if (!preg_match('/Program Ture\s*-\s*(?P<month>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<year>\d{4})/ui', $text, $matches)) {
            throw new \RuntimeException('Nu s-a putut găsi antetul cu luna și anul în PDF.');
        }

        $monthName = mb_strtolower($matches['month']);
        $year = (int) $matches['year'];

        if (!isset(self::MONTHS_MAP[$monthName])) {
            throw new \RuntimeException("Luna necunoscută: {$matches['month']}");
        }

        $monthNum = self::MONTHS_MAP[$monthName];

        $operators = User::query()->where('role', User::ROLE_OPERATOR)->get();

        $lines = explode("\n", $text);
        $importedCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^(?P<day>\d{1,2})\s+(?P<zi>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<noapte>[a-zA-ZăâîșțĂÂÎȘȚ]+)$/ui', $line, $rowMatches)) {
                $day = (int) $rowMatches['day'];
                $dateString = sprintf('%04d-%02d-%02d', $year, $monthNum, $day);

                $ziName = trim($rowMatches['zi']);
                $noapteName = trim($rowMatches['noapte']);

                $ziUser = $operators->first(fn($u) => mb_strtolower($u->name) === mb_strtolower($ziName));
                $noapteUser = $operators->first(fn($u) => mb_strtolower($u->name) === mb_strtolower($noapteName));

                WorkShift::query()->updateOrCreate(
                    ['date' => $dateString, 'shift_type' => 'zi'],
                    [
                        'user_id' => $ziUser?->id,
                        'raw_employee_name' => $ziName
                    ]
                );

                WorkShift::query()->updateOrCreate(
                    ['date' => $dateString, 'shift_type' => 'noapte'],
                    [
                        'user_id' => $noapteUser?->id,
                        'raw_employee_name' => $noapteName
                    ]
                );

                $importedCount++;
            }
        }

        if ($importedCount === 0) {
            throw new \RuntimeException('Nu s-au găsit rânduri valide de program de tură.');
        }

        return [
            'month_name' => $matches['month'],
            'year' => $year,
            'imported_days' => $importedCount,
        ];
    }
}
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec -T app php artisan test --filter=ParseSchedulePdfActionTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Actions/Scheduling/ParseSchedulePdfAction.php tests/Feature/Scheduling/ParseSchedulePdfActionTest.php
git commit -m "feat: implement PDF parsing action class and integration test"
```

---

### Task 4: Filament Page Actions on `OrdineaDeZi`

**Files:**
- Modify: [OrdineaDeZi.php](file:///d:/Automation/SkyPark/App/app/Filament/Pages/OrdineaDeZi.php)
- Create: [OrdineaDeZiUploadTest.php](file:///d:/Automation/SkyPark/App/tests/Feature/Scheduling/OrdineaDeZiUploadTest.php)

**Step 1: Write the failing test**
Create `tests/Feature/Scheduling/OrdineaDeZiUploadTest.php`:
```php
<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Models\WorkShift;
use Filament\Pages\Actions\Action;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Pages\OrdineaDeZi;

class OrdineaDeZiUploadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_admin_can_upload_pdf_schedule(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        Storage::fake('local');

        $pdfFile = new UploadedFile(
            base_path('MyFiles/Program_Iunie_2026_SkyCenter.pdf'),
            'Program_Iunie_2026_SkyCenter.pdf',
            'application/pdf',
            null,
            true
        );

        Livewire::actingAs($admin)
            ->test(OrdineaDeZi::class)
            ->callPageAction('uploadSchedule', [
                'schedule_pdf' => $pdfFile
            ])
            ->assertHasNoPageActionErrors();

        // Check database shifts populated
        $this->assertTrue(WorkShift::query()->where('date', '2026-06-01')->exists());
    }

    public function test_non_admin_cannot_access_page(): void
    {
        $operator = User::query()->where('role', User::ROLE_OPERATOR)->first();

        $this->actingAs($operator)
            ->get(OrdineaDeZi::getUrl())
            ->assertForbidden();
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiUploadTest`
Expected: FAIL (no uploadSchedule page action exists)

**Step 3: Write minimal implementation**
1. Add action imports and header actions logic to `app/Filament/Pages/OrdineaDeZi.php`:
```php
// Add to imports
use App\Actions\Scheduling\ParseSchedulePdfAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
```
2. Inject the action logic inside the class:
```php
    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadSchedule')
                ->label('Încarcă Program Ture')
                ->icon('heroicon-o-document-arrow-up')
                ->form([
                    FileUpload::make('schedule_pdf')
                        ->label('Fișier PDF Program Ture')
                        ->acceptedFileTypes(['application/pdf'])
                        ->required()
                        ->disk('local')
                        ->directory('temp-schedules'),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['schedule_pdf']);
                    
                    try {
                        $action = app(ParseSchedulePdfAction::class);
                        $result = $action->execute($filePath);

                        Notification::make()
                            ->title('Programul de ture a fost importat cu succes!')
                            ->body("Au fost importate turele pentru luna: {$result['month_name']} {$result['year']}.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Eroare la importul programului!')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    } finally {
                        Storage::disk('local')->delete($data['schedule_pdf']);
                    }
                })
        ];
    }
```
3. Update policy: ensure only admins can access the page if user role is not admin. Actually, checking `OrdineaDeZi.php::canAccess()`:
```php
    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof \App\Models\User 
            && $user->canAccessPanel(Filament::getCurrentPanel())
            && $user->isAdmin(); // restrict to admin only
    }
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiUploadTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Filament/Pages/OrdineaDeZi.php tests/Feature/Scheduling/OrdineaDeZiUploadTest.php
git commit -m "feat: implement header action for schedule upload and access control"
```

---

### Task 5: View Display Integration

**Files:**
- Modify: [OrdineaDeZi.php](file:///d:/Automation/SkyPark/App/app/Filament/Pages/OrdineaDeZi.php)
- Modify: [ordinea-de-zi.blade.php](file:///d:/Automation/SkyPark/App/resources/views/filament/pages/ordinea-de-zi.blade.php)
- Create: [OrdineaDeZiDisplayTest.php](file:///d:/Automation/SkyPark/App/tests/Feature/Scheduling/OrdineaDeZiDisplayTest.php)

**Step 1: Write the failing test**
Create `tests/Feature/Scheduling/OrdineaDeZiDisplayTest.php`:
```php
<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;
use App\Filament\Pages\OrdineaDeZi;

class OrdineaDeZiDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_retrieves_active_shifts_for_selected_date(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        $bratan = User::query()->where('name', 'Bratan')->first();
        $matei = User::query()->where('name', 'Matei')->first();

        // Seed explicit shift
        WorkShift::create(['date' => '2026-06-01', 'shift_type' => 'zi', 'user_id' => $bratan->id]);
        WorkShift::create(['date' => '2026-06-01', 'shift_type' => 'noapte', 'user_id' => $matei->id]);

        Livewire::actingAs($admin)
            ->test(OrdineaDeZi::class, ['selectedDate' => '2026-06-01'])
            ->assertSee('Bratan')
            ->assertSee('Matei');
    }
}
```

**Step 2: Run test to verify it fails**
Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiDisplayTest`
Expected: FAIL (does not display shifts / has no activeShifts getter)

**Step 3: Write minimal implementation**
1. Add `getActiveShifts()` helper in `app/Filament/Pages/OrdineaDeZi.php`:
```php
    /**
     * @return array{zi:string,noapte:string}
     */
    public function getActiveShifts(): array
    {
        $date = $this->selectedDate;

        $dayShift = WorkShift::query()
            ->with('user')
            ->where('date', $date)
            ->where('shift_type', 'zi')
            ->first();

        $nightShift = WorkShift::query()
            ->with('user')
            ->where('date', $date)
            ->where('shift_type', 'noapte')
            ->first();

        return [
            'zi' => $dayShift ? ($dayShift->user ? $dayShift->user->name : $dayShift->raw_employee_name) : 'Nealocat',
            'noapte' => $nightShift ? ($nightShift->user ? $nightShift->user->name : $nightShift->raw_employee_name) : 'Nealocat',
        ];
    }
```
2. Render shifts at the top of `resources/views/filament/pages/ordinea-de-zi.blade.php`:
Add before the date selector:
```html
    @php
        $shifts = $this->getActiveShifts();
    @endphp

    {{-- Program Ture --}}
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
        <div class="fi-fo-field-wrp bg-white dark:bg-gray-900 border dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-amber-50 dark:bg-amber-950/20 text-amber-500 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386-1.591 1.591M21 12h-2.25m-.386 6.364-1.591-1.591M12 18.75V21m-4.773-4.227-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0Z" />
                </svg>
            </div>
            <div>
                <span class="text-xs text-gray-400 block uppercase tracking-wider font-semibold">Tura Zi (09:00 - 21:00)</span>
                <span class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ $shifts['zi'] }}</span>
            </div>
        </div>

        <div class="fi-fo-field-wrp bg-white dark:bg-gray-900 border dark:border-gray-800 rounded-xl p-4 shadow-sm flex items-center gap-4">
            <div class="p-3 bg-indigo-50 dark:bg-indigo-950/20 text-indigo-500 rounded-lg">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.752 15.002A9.72 9.72 0 0 1 18 15.75c-5.385 0-9.75-4.365-9.75-9.75 0-1.33.266-2.597.748-3.752A9.753 9.753 0 0 0 3 11.25C3 16.635 7.365 21 12.75 21a9.753 9.753 0 0 0 9.002-5.998Z" />
                </svg>
            </div>
            <div>
                <span class="text-xs text-gray-400 block uppercase tracking-wider font-semibold">Tura Noapte (21:00 - 09:00)</span>
                <span class="text-lg font-bold text-gray-800 dark:text-gray-200">{{ $shifts['noapte'] }}</span>
            </div>
        </div>
    </div>
```

**Step 4: Run test to verify it passes**
Run: `docker compose exec -T app php artisan test --filter=OrdineaDeZiDisplayTest`
Expected: PASS

**Step 5: Commit**
```bash
git add app/Filament/Pages/OrdineaDeZi.php resources/views/filament/pages/ordinea-de-zi.blade.php tests/Feature/Scheduling/OrdineaDeZiDisplayTest.php
git commit -m "feat: display work shifts dynamically on OrdineaDeZi dashboard"
```
