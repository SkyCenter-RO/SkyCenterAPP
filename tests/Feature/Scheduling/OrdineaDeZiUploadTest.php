<?php

namespace Tests\Feature\Scheduling;

use App\Models\User;
use App\Models\WorkShift;
use Filament\Facades\Filament;
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
        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_admin_can_upload_pdf_schedule(): void
    {
        $admin = User::query()->where('role', User::ROLE_ADMIN)->first();
        Storage::fake('local');

        $realPdfPath = base_path('MyFiles/Program_Iunie_2026_SkyCenter.pdf');
        $pdfFile = UploadedFile::fake()->create('Program_Iunie_2026_SkyCenter.pdf', filesize($realPdfPath), 'application/pdf');
        file_put_contents($pdfFile->getRealPath(), file_get_contents($realPdfPath));


        Livewire::actingAs($admin)
            ->test(OrdineaDeZi::class)
            ->assertActionVisible('uploadSchedule')
            ->callAction('uploadSchedule', [
                'schedule_pdf' => $pdfFile
            ])
            ->assertHasNoActionErrors();

        // Check database shifts populated
        $this->assertTrue(WorkShift::query()->where('date', '2026-06-01')->exists());
    }

    public function test_operator_cannot_see_upload_action(): void
    {
        $operator = User::query()->where('role', User::ROLE_OPERATOR)->first();

        Livewire::actingAs($operator)
            ->test(OrdineaDeZi::class)
            ->assertActionHidden('uploadSchedule');
    }
}

