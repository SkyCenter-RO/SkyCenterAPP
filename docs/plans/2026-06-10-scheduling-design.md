# Design Specification: Shift Scheduling (Orar / Ture)

Implement the daily shift scheduling system (Subproject #5) allowing admins to upload the PDF shift schedule, parse it, and display active shifts on the "Ordinea de zi" page.

## 1. Database Schema & Seeders

### Work Shifts Migration
Create a migration for the `work_shifts` table:
```php
Schema::create('work_shifts', function (Blueprint $table) {
    $table->id();
    $table->date('date')->index();
    $table->string('shift_type'); // 'zi' or 'noapte'
    $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
    $table->string('raw_employee_name')->nullable();
    $table->timestamps();

    $table->unique(['date', 'shift_type']);
});
```

### Seeder: OperatorUserSeeder
Ensure the 4 operators exist in the `users` table:
- **Bratan** (`bratan@skycenter.local`)
- **Bogdan** (`bogdan@skycenter.local`)
- **Matei** (`matei@skycenter.local`)
- **Catalin** (`catalin@skycenter.local`)

All seeded with the `operator` role and active status.

---

## 2. PDF Parsing Action

A dedicated action `App\Actions\Scheduling\ParseSchedulePdfAction` will parse the schedule:
1. Parse the month/year header using regex: `Program Ture\s*-\s*(?P<month>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<year>\d{4})`
2. Parse day-by-day operator rows: `^\s*(?P<day>\d{1,2})\s+(?P<zi>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s+(?P<noapte>[a-zA-ZăâîșțĂÂÎȘȚ]+)\s*$`
3. Query the database for the corresponding user accounts by mapping names case-insensitively.
4. Insert/update (upsert) the shifts for the parsed month.

---

## 3. UI Display & Import

### Filament Page Actions
Add a header Action button on the `Ordinea de zi` page (`app/Filament/Pages/OrdineaDeZi.php`):
- Visible to admins only.
- Accepts PDF upload.
- Triggers the `ParseSchedulePdfAction`.
- Displays a success notification.

### View Integration
Render a card section at the top of the `Ordinea de zi` Blade view:
- Displays **Tura Zi** and **Tura Noapte** for the selected date.
- Dynamically updates as the selected date is changed.
