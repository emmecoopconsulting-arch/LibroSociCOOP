<x-filament-panels::page>
    @php
        $eventsByDay = $this->eventsByDay();
        $month = $this->currentMonth();
        $selectedDayEvents = $this->selectedDayEvents();
        $eventTypeLabels = [
            'permesso' => 'Permesso',
            'contratto' => 'Contratto',
            'visita' => 'Visita',
        ];
    @endphp

    <style>
        .scadenziario-shell {
            display: flex;
            flex-direction: column;
            gap: 24px;
            width: 100%;
        }

        .scadenziario-toolbar {
            align-items: center;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: space-between;
        }

        .scadenziario-month {
            color: rgb(17 24 39);
            font-size: 22px;
            font-weight: 700;
            line-height: 1.2;
            margin: 0;
        }

        .scadenziario-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .calendar-frame {
            background: white;
            border: 1px solid rgb(229 231 235);
            border-radius: 10px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
            overflow: hidden;
            width: 100%;
        }

        .calendar-grid {
            display: grid;
            grid-template-columns: repeat(7, minmax(120px, 1fr));
            width: 100%;
        }

        .calendar-head {
            background: rgb(249 250 251);
            border-bottom: 1px solid rgb(229 231 235);
            color: rgb(75 85 99);
            font-size: 13px;
            font-weight: 700;
            padding: 12px;
        }

        .calendar-cell {
            background: white;
            border-bottom: 1px solid rgb(229 231 235);
            border-right: 1px solid rgb(229 231 235);
            min-height: 150px;
            padding: 0;
            position: relative;
        }

        .calendar-cell:nth-child(7n) {
            border-right: 0;
        }

        .calendar-cell.is-empty {
            background: rgb(249 250 251);
        }

        .calendar-day {
            background: transparent;
            border: 0;
            cursor: pointer;
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-height: 150px;
            padding: 12px;
            text-align: left;
            width: 100%;
        }

        .calendar-day:hover {
            background: rgb(255 251 235);
        }

        .calendar-day.is-selected {
            background: rgb(254 243 199);
            box-shadow: inset 0 0 0 2px rgb(217 119 6);
        }

        .calendar-date {
            align-items: center;
            color: rgb(17 24 39);
            display: flex;
            font-size: 15px;
            font-weight: 700;
            justify-content: space-between;
        }

        .calendar-counter {
            background: rgb(217 119 6);
            border-radius: 999px;
            color: white;
            font-size: 11px;
            font-weight: 700;
            line-height: 1;
            min-width: 20px;
            padding: 5px 7px;
            text-align: center;
        }

        .calendar-events {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .calendar-event {
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.25;
            overflow: hidden;
            padding: 6px 8px;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .calendar-event.permesso {
            background: rgb(254 243 199);
            color: rgb(120 53 15);
        }

        .calendar-event.contratto {
            background: rgb(224 242 254);
            color: rgb(12 74 110);
        }

        .calendar-event.visita {
            background: rgb(209 250 229);
            color: rgb(6 95 70);
        }

        .calendar-event.is-overdue {
            background: rgb(254 226 226);
            color: rgb(153 27 27);
        }

        .calendar-more {
            color: rgb(75 85 99);
            font-size: 12px;
            font-weight: 600;
        }

        .deadline-panel {
            background: white;
            border: 1px solid rgb(229 231 235);
            border-radius: 10px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
            padding: 16px;
        }

        .deadline-panel h3 {
            color: rgb(17 24 39);
            font-size: 16px;
            font-weight: 700;
            margin: 0 0 12px;
        }

        .deadline-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .deadline-row {
            align-items: center;
            border: 1px solid rgb(229 231 235);
            border-radius: 8px;
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 10px 12px;
        }

        .deadline-label {
            color: rgb(17 24 39);
            font-size: 14px;
            font-weight: 600;
        }

        .deadline-date {
            color: rgb(75 85 99);
            font-size: 14px;
            font-weight: 600;
            white-space: nowrap;
        }

        .deadline-empty {
            color: rgb(107 114 128);
            font-size: 14px;
        }

        .event-chip {
            border-radius: 999px;
            color: white;
            font-size: 12px;
            font-weight: 700;
            padding: 4px 8px;
            white-space: nowrap;
        }

        .event-chip.permesso {
            background: rgb(217 119 6);
        }

        .event-chip.contratto {
            background: rgb(2 132 199);
        }

        .event-chip.visita {
            background: rgb(5 150 105);
        }

        .event-chip.is-overdue {
            background: rgb(220 38 38);
        }

        @media (max-width: 900px) {
            .calendar-frame {
                overflow-x: auto;
            }

            .calendar-grid {
                min-width: 840px;
            }
        }
    </style>

    <div class="scadenziario-shell">
        <div class="scadenziario-toolbar">
            <h2 class="scadenziario-month">{{ ucfirst($month->translatedFormat('F Y')) }}</h2>

            <div class="scadenziario-actions">
                <x-filament::button color="gray" icon="heroicon-o-chevron-left" wire:click="previousMonth">
                    Mese precedente
                </x-filament::button>
                <x-filament::button color="gray" icon="heroicon-o-chevron-right" icon-position="after" wire:click="nextMonth">
                    Mese successivo
                </x-filament::button>
            </div>
        </div>

        <div class="calendar-frame">
            <div class="calendar-grid">
                @foreach (['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'] as $dayName)
                    <div class="calendar-head">{{ $dayName }}</div>
                @endforeach

                @foreach ($this->weeks() as $week)
                    @foreach ($week as $day)
                        @if ($day)
                            @php
                                $date = $day->toDateString();
                                $dayEvents = $eventsByDay[$date] ?? [];
                            @endphp

                            <div class="calendar-cell">
                                <button
                                    class="calendar-day {{ $this->selectedDate === $date ? 'is-selected' : '' }}"
                                    type="button"
                                    wire:click="selectDate('{{ $date }}')"
                                >
                                    <span class="calendar-date">
                                        <span>{{ $day->day }}</span>
                                        @if (count($dayEvents) > 0)
                                            <span class="calendar-counter">{{ count($dayEvents) }}</span>
                                        @endif
                                    </span>

                                    <span class="calendar-events">
                                        @foreach (array_slice($dayEvents, 0, 3) as $event)
                                            <span class="calendar-event {{ $event['type'] }} {{ $event['is_overdue'] ? 'is-overdue' : '' }}">
                                                {{ $event['label'] }}
                                            </span>
                                        @endforeach

                                        @if (count($dayEvents) > 3)
                                            <span class="calendar-more">+{{ count($dayEvents) - 3 }} altre scadenze</span>
                                        @endif
                                    </span>
                                </button>
                            </div>
                        @else
                            <div class="calendar-cell is-empty"></div>
                        @endif
                    @endforeach
                @endforeach
            </div>
        </div>

        <div class="deadline-panel">
            <h3>
                @if ($this->selectedDate)
                    Scadenze del {{ \Carbon\CarbonImmutable::parse($this->selectedDate)->format('d/m/Y') }}
                @else
                    Seleziona un giorno
                @endif
            </h3>

            <div class="deadline-list">
                @forelse ($selectedDayEvents as $event)
                    <div class="deadline-row">
                        <div class="deadline-label">{{ $event['label'] }}</div>
                        <div class="event-chip {{ $event['type'] }} {{ $event['is_overdue'] ? 'is-overdue' : '' }}">
                            {{ $event['is_overdue'] ? 'Scaduta' : ($eventTypeLabels[$event['type']] ?? 'Scadenza') }}
                        </div>
                    </div>
                @empty
                    <div class="deadline-empty">Nessuna scadenza per il giorno selezionato.</div>
                @endforelse
            </div>
        </div>

        <div class="deadline-panel">
            <h3>Scadute e prossime scadenze</h3>

            <div class="deadline-list">
                @forelse ($this->upcomingDeadlines() as $event)
                    <div class="deadline-row">
                        <span class="deadline-label">{{ $event['label'] }}</span>
                        <span class="deadline-date">
                            @if ($event['is_overdue'])
                                Scaduta -
                            @endif
                            {{ \Carbon\CarbonImmutable::parse($event['date'])->format('d/m/Y') }}
                        </span>
                    </div>
                @empty
                    <div class="deadline-empty">Nessuna scadenza scaduta o nei prossimi 90 giorni.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>
