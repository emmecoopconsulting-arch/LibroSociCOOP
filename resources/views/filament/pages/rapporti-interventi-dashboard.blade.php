<x-filament-panels::page>
    @php
        $totalHours = $this->totalHours();
        $reportsCount = $this->reportsCount();
        $operatorsCount = $this->operatorsCount();
        $hoursByClient = $this->hoursByClient();
        $hoursByOperator = $this->hoursByOperator();
        $reportRows = $this->reportRows();
    @endphp

    <style>
        .reports-dashboard {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .reports-filters,
        .reports-stats,
        .reports-grid,
        .reports-table {
            display: grid;
            gap: 12px;
        }

        .reports-filters {
            align-items: end;
            grid-template-columns: repeat(5, minmax(160px, 1fr));
        }

        .reports-stats {
            grid-template-columns: repeat(3, minmax(160px, 1fr));
        }

        .reports-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .reports-panel {
            background: white;
            border: 1px solid rgb(229 231 235);
            border-radius: 8px;
            box-shadow: 0 1px 2px rgb(15 23 42 / 0.06);
            padding: 16px;
        }

        .reports-label {
            color: rgb(75 85 99);
            display: block;
            font-size: 13px;
            font-weight: 700;
            margin-bottom: 6px;
        }

        .reports-input {
            border: 1px solid rgb(209 213 219);
            border-radius: 8px;
            font-size: 14px;
            min-height: 40px;
            padding: 8px 10px;
            width: 100%;
        }

        .reports-button {
            background: rgb(217 119 6);
            border: 0;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            min-height: 40px;
            padding: 8px 12px;
        }

        .reports-stat-value {
            color: rgb(17 24 39);
            font-size: 28px;
            font-weight: 800;
            line-height: 1.1;
        }

        .reports-title {
            color: rgb(17 24 39);
            font-size: 16px;
            font-weight: 800;
            margin: 0 0 12px;
        }

        .reports-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .reports-row {
            align-items: center;
            border-bottom: 1px solid rgb(243 244 246);
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 8px 0;
        }

        .reports-row:last-child {
            border-bottom: 0;
        }

        .reports-row-name {
            color: rgb(17 24 39);
            font-size: 14px;
            font-weight: 700;
        }

        .reports-row-meta {
            color: rgb(75 85 99);
            font-size: 12px;
            margin-top: 2px;
        }

        .reports-hours {
            color: rgb(146 64 14);
            font-size: 15px;
            font-weight: 800;
            white-space: nowrap;
        }

        .reports-table table {
            border-collapse: collapse;
            width: 100%;
        }

        .reports-table th,
        .reports-table td {
            border-bottom: 1px solid rgb(229 231 235);
            color: rgb(17 24 39);
            font-size: 13px;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }

        .reports-table th {
            background: rgb(249 250 251);
            color: rgb(75 85 99);
            font-weight: 800;
        }

        @media (max-width: 1100px) {
            .reports-filters,
            .reports-grid {
                grid-template-columns: 1fr;
            }

            .reports-stats {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .reports-stats {
                grid-template-columns: 1fr;
            }

            .reports-table {
                overflow-x: auto;
            }
        }
    </style>

    <div class="reports-dashboard">
        <div class="reports-panel reports-filters">
            <label>
                <span class="reports-label">Dal</span>
                <input class="reports-input" type="date" wire:model.live="dateFrom">
            </label>

            <label>
                <span class="reports-label">Al</span>
                <input class="reports-input" type="date" wire:model.live="dateTo">
            </label>

            <label>
                <span class="reports-label">Cliente / cantiere</span>
                <select class="reports-input" wire:model.live="workSiteId">
                    <option value="">Tutti</option>
                    @foreach ($this->siteOptions() as $site)
                        <option value="{{ $site['id'] }}">{{ $site['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <label>
                <span class="reports-label">Dipendente</span>
                <select class="reports-input" wire:model.live="socioId">
                    <option value="">Tutti</option>
                    @foreach ($this->operatorOptions() as $operator)
                        <option value="{{ $operator['id'] }}">{{ $operator['label'] }}</option>
                    @endforeach
                </select>
            </label>

            <button class="reports-button" type="button" wire:click="resetFilters">Reimposta</button>
        </div>

        <div class="reports-stats">
            <div class="reports-panel">
                <span class="reports-label">Ore da fatturare</span>
                <div class="reports-stat-value">{{ number_format($totalHours, 2, ',', '.') }}</div>
            </div>

            <div class="reports-panel">
                <span class="reports-label">Rapporti</span>
                <div class="reports-stat-value">{{ $reportsCount }}</div>
            </div>

            <div class="reports-panel">
                <span class="reports-label">Dipendenti coinvolti</span>
                <div class="reports-stat-value">{{ $operatorsCount }}</div>
            </div>
        </div>

        <div class="reports-grid">
            <div class="reports-panel">
                <h2 class="reports-title">Ore per cliente / cantiere</h2>
                <div class="reports-list">
                    @forelse ($hoursByClient as $row)
                        <div class="reports-row">
                            <div>
                                <div class="reports-row-name">{{ $row['label'] }}</div>
                                <div class="reports-row-meta">{{ $row['reports'] }} rapporti</div>
                            </div>
                            <div class="reports-hours">{{ number_format($row['hours'], 2, ',', '.') }} ore</div>
                        </div>
                    @empty
                        <div class="reports-row-meta">Nessun dato nel periodo selezionato.</div>
                    @endforelse
                </div>
            </div>

            <div class="reports-panel">
                <h2 class="reports-title">Ore per dipendente</h2>
                <div class="reports-list">
                    @forelse ($hoursByOperator as $row)
                        <div class="reports-row">
                            <div class="reports-row-name">{{ $row['label'] }}</div>
                            <div class="reports-hours">{{ number_format($row['hours'], 2, ',', '.') }} ore</div>
                        </div>
                    @empty
                        <div class="reports-row-meta">Nessun dato nel periodo selezionato.</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="reports-panel reports-table">
            <h2 class="reports-title">Dettaglio rapporti</h2>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Protocollo</th>
                        <th>Cliente / cantiere</th>
                        <th>Oggetto</th>
                        <th>Operatori</th>
                        <th>Ore</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reportRows as $row)
                        <tr>
                            <td>{{ $row['date'] }}</td>
                            <td>{{ $row['protocollo'] }}</td>
                            <td>{{ $row['site'] }}</td>
                            <td>{{ $row['object'] }}</td>
                            <td>{{ $row['operators'] }}</td>
                            <td>{{ number_format($row['hours'], 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Nessun rapporto nel periodo selezionato.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
