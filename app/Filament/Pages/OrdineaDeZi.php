<?php

namespace App\Filament\Pages;

use App\Models\LodgingReservation;
use App\Models\ParkingLot;
use App\Models\ParkingReservation;
use App\Models\RentContract;
use App\Models\RentVehicle;
use App\Models\Room;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Livewire\Attributes\Url;
use App\Actions\Scheduling\ParseSchedulePdfAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class OrdineaDeZi extends Page
{
    protected static \BackedEnum|string|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Ordinea de zi';

    protected static ?int $navigationSort = -100;

    protected static ?string $slug = 'ordinea-de-zi';

    protected string $view = 'filament.pages.ordinea-de-zi';

    private const TZ = 'Europe/Bucharest';

    #[Url(keep: true)]
    public ?string $selectedDate = null;

    public string $activeService = 'parcare';

    public function mount(): void
    {
        $this->selectedDate = $this->normalizeDate($this->selectedDate);
    }

    public function updatedSelectedDate(?string $value): void
    {
        $this->selectedDate = $this->normalizeDate($value);
    }

    public function getTitle(): string
    {
        return 'Ordinea de zi';
    }

    private function normalizeDate(?string $value): string
    {
        try {
            return CarbonImmutable::parse($value ?: 'now', self::TZ)->toDateString();
        } catch (\Throwable) {
            return CarbonImmutable::now(self::TZ)->toDateString();
        }
    }

    private function selectedDay(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->selectedDate ?: 'now', self::TZ)->startOfDay();
    }

    /**
     * @return list<array{label:string,date:string,isActive:bool}>
     */
    public function getDateShortcuts(): array
    {
        $today = CarbonImmutable::now(self::TZ)->startOfDay();

        return [
            ['label' => 'Azi', 'date' => $today->toDateString(), 'isActive' => $this->selectedDate === $today->toDateString()],
            ['label' => 'Mâine', 'date' => $today->addDay()->toDateString(), 'isActive' => $this->selectedDate === $today->addDay()->toDateString()],
            ['label' => 'Poimâine', 'date' => $today->addDays(2)->toDateString(), 'isActive' => $this->selectedDate === $today->addDays(2)->toDateString()],
        ];
    }

    /**
     * Parcare: rezervări active (parked/booked) care intersectează ziua selectată.
     *
     * @return array{totalSpaces:int,occupied:int,zones:list<array{lot:string,occupied:int,total:int}>}
     */
    public function getParkingAvailability(): array
    {
        $day = $this->selectedDay();
        $dayEnd = $day->endOfDay();

        $totalSpaces = (int) ParkingLot::query()->sum('total_spaces');

        $occupied = ParkingReservation::query()
            ->whereIn('status', ['booked', 'parked'])
            ->where(function ($q) use ($dayEnd): void {
                $q->whereNull('check_in_at')->orWhere('check_in_at', '<=', $dayEnd);
            })
            ->where(function ($q) use ($day): void {
                $q->whereNull('check_out_at')->orWhere('check_out_at', '>=', $day);
            })
            ->count();

        $zones = ParkingLot::query()
            ->withCount(['reservations as occupied_count' => function ($q) use ($day, $dayEnd): void {
                $q->whereIn('status', ['booked', 'parked'])
                    ->where(function ($qq) use ($dayEnd): void {
                        $qq->whereNull('check_in_at')->orWhere('check_in_at', '<=', $dayEnd);
                    })
                    ->where(function ($qq) use ($day): void {
                        $qq->whereNull('check_out_at')->orWhere('check_out_at', '>=', $day);
                    });
            }])
            ->get()
            ->map(fn (ParkingLot $lot): array => [
                'lot' => $lot->name,
                'occupied' => (int) $lot->occupied_count,
                'total' => (int) $lot->total_spaces,
            ])
            ->all();

        return ['totalSpaces' => $totalSpaces, 'occupied' => $occupied, 'zones' => $zones];
    }

    /**
     * @return array{checkIns:list<array{guest:string,room:string,status:string}>,checkOuts:list<array{guest:string,room:string,status:string}>}
     */
    public function getLodgingEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (LodgingReservation $r): array => [
            'guest' => $r->guest_name ?? 'fără nume',
            'room' => $r->room?->name ?? 'fără cameră',
            'status' => $r->status ?? '-',
        ];

        return [
            'checkIns' => LodgingReservation::query()->with('room')
                ->whereDate('check_in', $date)->get()->map($map)->values()->all(),
            'checkOuts' => LodgingReservation::query()->with('room')
                ->whereDate('check_out', $date)->get()->map($map)->values()->all(),
        ];
    }

    /**
     * @return array{occupiedRooms:int,totalRooms:int}
     */
    public function getLodgingAvailability(): array
    {
        $date = $this->selectedDay()->toDateString();

        $occupied = LodgingReservation::query()
            ->whereIn('status', ['confirmed', 'checked_in'])
            ->whereDate('check_in', '<=', $date)
            ->whereDate('check_out', '>', $date)
            ->distinct('room_id')
            ->count('room_id');

        return [
            'occupiedRooms' => $occupied,
            'totalRooms' => (int) Room::query()->where('is_active', true)->count(),
        ];
    }

    /**
     * @return array{checkIns:list<array{client:string,vehicle:string,status:string}>,checkOuts:list<array{client:string,vehicle:string,status:string}>,available:int,total:int}
     */
    public function getRentEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (RentContract $c): array => [
            'client' => $c->client?->name ?? 'fără client',
            'vehicle' => $c->vehicle?->license_plate ?? ($c->vehicle?->brand ?? 'fără mașină'),
            'status' => $c->status ?? '-',
        ];

        return [
            'checkIns' => RentContract::query()->with(['client', 'vehicle'])
                ->whereDate('start_date', $date)->get()->map($map)->values()->all(),
            'checkOuts' => RentContract::query()->with(['client', 'vehicle'])
                ->whereDate('end_date', $date)->get()->map($map)->values()->all(),
            'available' => (int) RentVehicle::query()->where('status', 'available')->count(),
            'total' => (int) RentVehicle::query()->count(),
        ];
    }

    /**
     * @return array{checkIns:list<array{label:string,detail:string,status:string}>,checkOuts:list<array{label:string,detail:string,status:string}>}
     */
    public function getParkingEvents(): array
    {
        $date = $this->selectedDay()->toDateString();

        $map = fn (ParkingReservation $r): array => [
            'label' => $r->plate ?? 'fără număr',
            'detail' => $r->customer?->name ?? 'fără client',
            'status' => $r->status ?? '-',
        ];

        return [
            'checkIns' => ParkingReservation::query()->with('customer')
                ->whereDate('check_in_at', $date)->get()->map($map)->values()->all(),
            'checkOuts' => ParkingReservation::query()->with('customer')
                ->whereDate('check_out_at', $date)->get()->map($map)->values()->all(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('uploadSchedule')
                ->label('Încarcă Program Ture')
                ->icon('heroicon-o-document-arrow-up')
                ->visible(fn () => Filament::auth()->user()?->isAdmin())
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

    /**
     * @return array{zi:string,noapte:string}
     */
    public function getActiveShifts(): array
    {
        $date = $this->selectedDate;

        $dayShift = \App\Models\WorkShift::query()
            ->with('user')
            ->where('date', $date)
            ->where('shift_type', 'zi')
            ->first();

        $nightShift = \App\Models\WorkShift::query()
            ->with('user')
            ->where('date', $date)
            ->where('shift_type', 'noapte')
            ->first();

        return [
            'zi' => $dayShift ? ($dayShift->user ? $dayShift->user->name : $dayShift->raw_employee_name) : 'Nealocat',
            'noapte' => $nightShift ? ($nightShift->user ? $nightShift->user->name : $nightShift->raw_employee_name) : 'Nealocat',
        ];
    }

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        $panel = Filament::getCurrentPanel();

        return $user instanceof \App\Models\User 
            && $user->is_active 
            && ($panel ? $user->canAccessPanel($panel) : $user->hasValidRole());
    }
}
