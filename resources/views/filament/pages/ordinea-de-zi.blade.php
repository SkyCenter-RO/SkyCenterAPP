<x-filament-panels::page>
    @php
        $parking = $this->getParkingAvailability();
        $parkingEvents = $this->getParkingEvents();
        $lodging = $this->getLodgingAvailability();
        $lodgingEvents = $this->getLodgingEvents();
        $rent = $this->getRentEvents();
    @endphp

    {{-- Selector de dată --}}
    <div class="flex flex-wrap items-center gap-3 mb-4">
        <div class="flex gap-2">
            @foreach ($this->getDateShortcuts() as $shortcut)
                <button type="button" wire:click="$set('selectedDate', '{{ $shortcut['date'] }}')"
                    @class([
                        'fi-btn fi-btn-size-md rounded-lg px-3 py-2 text-sm font-medium',
                        'bg-primary-600 text-white' => $shortcut['isActive'],
                        'bg-gray-100 dark:bg-gray-800' => ! $shortcut['isActive'],
                    ])>
                    {{ $shortcut['label'] }}
                </button>
            @endforeach
        </div>
        <input type="date" wire:model.live="selectedDate"
            class="fi-input rounded-lg border-gray-300 dark:bg-gray-900" />
        <span class="text-sm text-gray-500">Ziua selectată: <strong>{{ $this->selectedDate }}</strong></span>
    </div>

    {{-- Tab-uri pe servicii --}}
    <div class="flex gap-2 border-b border-gray-200 dark:border-gray-700 mb-4">
        @foreach (['parcare' => 'Parcare', 'cazare' => 'Cazare', 'rent' => 'Rent-a-car'] as $key => $label)
            <button type="button" wire:click="$set('activeService', '{{ $key }}')"
                @class([
                    'px-4 py-2 text-sm font-medium border-b-2 -mb-px',
                    'border-primary-600 text-primary-600' => $activeService === $key,
                    'border-transparent text-gray-500' => $activeService !== $key,
                ])>
                {{ $label }}
            </button>
        @endforeach
    </div>

    @if ($activeService === 'parcare')
        <x-filament::section heading="Disponibilitate parcare">
            <div class="mb-3 text-sm">Total ocupate: <strong>{{ $parking['occupied'] }}</strong> / {{ $parking['totalSpaces'] }} locuri</div>
            @foreach ($parking['zones'] as $zone)
                @php $pct = $zone['total'] > 0 ? min(100, round($zone['occupied'] / $zone['total'] * 100)) : 0; @endphp
                <div class="mb-2">
                    <div class="flex justify-between text-sm"><span>{{ $zone['lot'] }}</span><span>{{ $zone['occupied'] }} / {{ $zone['total'] }}</span></div>
                    <div class="h-2 rounded bg-gray-200 dark:bg-gray-700">
                        <div class="h-2 rounded bg-primary-600" style="width: {{ $pct }}%"></div>
                    </div>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section heading="Evenimente parcare ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Check-in</h4>
                    @forelse ($parkingEvents['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['label'] }} — {{ $e['detail'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Niciun check-in.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Check-out</h4>
                    @forelse ($parkingEvents['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['label'] }} — {{ $e['detail'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Niciun check-out.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @elseif ($activeService === 'cazare')
        <x-filament::section heading="Disponibilitate cazare">
            <div class="text-sm">Camere ocupate: <strong>{{ $lodging['occupiedRooms'] }}</strong> / {{ $lodging['totalRooms'] }}</div>
        </x-filament::section>
        <x-filament::section heading="Sosiri & plecări cazare ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Sosiri (check-in)</h4>
                    @forelse ($lodgingEvents['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['guest'] }} — {{ $e['room'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio sosire.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Plecări (check-out)</h4>
                    @forelse ($lodgingEvents['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['guest'] }} — {{ $e['room'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio plecare.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @else
        <x-filament::section heading="Disponibilitate rent-a-car">
            <div class="text-sm">Mașini disponibile: <strong>{{ $rent['available'] }}</strong> / {{ $rent['total'] }}</div>
        </x-filament::section>
        <x-filament::section heading="Preluări & predări ({{ $this->selectedDate }})" class="mt-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <h4 class="font-semibold mb-2">Preluări (start contract)</h4>
                    @forelse ($rent['checkIns'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['client'] }} — {{ $e['vehicle'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio preluare.</div>
                    @endforelse
                </div>
                <div>
                    <h4 class="font-semibold mb-2">Predări (final contract)</h4>
                    @forelse ($rent['checkOuts'] as $e)
                        <div class="text-sm py-1 border-b">{{ $e['client'] }} — {{ $e['vehicle'] }} <span class="text-gray-400">({{ $e['status'] }})</span></div>
                    @empty
                        <div class="text-sm text-gray-400">Nicio predare.</div>
                    @endforelse
                </div>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
