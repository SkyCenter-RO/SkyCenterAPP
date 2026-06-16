<?php

namespace Tests\Feature\Scheduling;

use App\Actions\Scheduling\ParseSchedulePdfAction;
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

        $action = new ParseSchedulePdfAction;
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
