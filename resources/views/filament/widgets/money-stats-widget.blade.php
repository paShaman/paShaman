<x-filament::section>
    <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; margin-bottom: 1rem;">
        <x-filament::section.heading>📊 Заработок за {{ $selectedYear }} год</x-filament::section.heading>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="selectedYear" wire:change="selectYear($event.target.value)">
                @foreach ($availableYears as $year)
                    <option value="{{ $year }}" @selected($selectedYear === $year)>{{ $year }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @if ($stats)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">

            {{-- Общий доход --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #eff6ff, #eef2ff); border-radius: 8px; border-left: 4px solid #3b82f6;">
                <div style="font-size: 14px; font-weight: 500; color: #3b82f6;">💰 Общий доход</div>
                <div style="font-size: 22px; font-weight: 700; color: #1d4ed8; margin: 0.25rem 0;">
                    {{ number_format($stats['per_year'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['per_year'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['per_year'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['per_year'] >= 0 ? '↑' : '↓' }} {{ abs($stats['pct']['per_year']) }}%
                    </span>
                @endif
            </div>

            {{-- Работа в месяц --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border-radius: 8px; border-left: 4px solid #10b981;">
                <div style="font-size: 14px; font-weight: 500; color: #10b981;">💼 Работа в месяц</div>
                <div style="font-size: 22px; font-weight: 700; color: #047857; margin: 0.25rem 0;">
                    {{ number_format($stats['per_month_work'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['per_month_work'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['per_month_work'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['per_month_work'] >= 0 ? '↑' : '↓' }} {{ abs($stats['pct']['per_month_work']) }}%
                    </span>
                @endif
            </div>

            {{-- Всего в месяц --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #f5f3ff, #faf5ff); border-radius: 8px; border-left: 4px solid #8b5cf6;">
                <div style="font-size: 14px; font-weight: 500; color: #8b5cf6;">💵 Всего в месяц</div>
                <div style="font-size: 22px; font-weight: 700; color: #6d28d9; margin: 0.25rem 0;">
                    {{ number_format($stats['per_month_all'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['per_month_all'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['per_month_all'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['per_month_all'] >= 0 ? '↑' : '↓' }} {{ abs($stats['pct']['per_month_all']) }}%
                    </span>
                @endif
            </div>

            {{-- Delta --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, {{ $stats['delta'] >= 0 ? '#f0fdf4, #ecfdf5' : '#fef2f2, #fff1f2' }}); border-radius: 8px; border-left: 4px solid {{ $stats['delta'] >= 0 ? '#22c55e' : '#ef4444' }};">
                <div style="font-size: 14px; font-weight: 500; color: {{ $stats['delta'] >= 0 ? '#16a34a' : '#dc2626' }};">↕️ Delta</div>
                <div style="font-size: 22px; font-weight: 700; color: {{ $stats['delta'] >= 0 ? '#16a34a' : '#dc2626' }}; margin: 0.25rem 0;">
                    {{ $stats['delta'] >= 0 ? '+' : '' }}{{ number_format($stats['delta'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['delta'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['delta'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['delta'] >= 0 ? '↑' : '↓' }} {{ abs($stats['pct']['delta']) }}%
                    </span>
                @endif
            </div>

            {{-- Не оплачено --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #fffbeb, #fefce8); border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 14px; font-weight: 500; color: #d97706;">⏳ Не оплачено</div>
                <div style="font-size: 22px; font-weight: 700; color: #b45309; margin: 0.25rem 0;">
                    {{ number_format($stats['not_payed'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['not_payed'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['not_payed'] <= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['not_payed'] <= 0 ? '↓' : '↑' }} {{ abs($stats['pct']['not_payed']) }}%
                    </span>
                @endif
            </div>

            {{-- Не оплачено (+план) — серый, справочно --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #f9fafb, #f8fafc); border-radius: 8px; border-left: 4px solid #9ca3af;">
                <div style="font-size: 14px; font-weight: 500; color: #4b5563;">📅 Не оплачено (+план)</div>
                <div style="font-size: 22px; font-weight: 700; color: #374151; margin: 0.25rem 0;">
                    {{ number_format($stats['not_payed_plan'], 0, ',', ' ') }} ₽
                </div>
                @if (($stats['pct']['not_payed_plan'] ?? null) !== null)
                    <span style="font-size: 13px; font-weight: 600; color: {{ $stats['pct']['not_payed_plan'] <= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $stats['pct']['not_payed_plan'] <= 0 ? '↓' : '↑' }} {{ abs($stats['pct']['not_payed_plan']) }}%
                    </span>
                @endif
            </div>

        </div>
    @else
        <x-filament::section>
            <div style="text-align: center; color: var(--gray-500); padding: 1rem 0;">
                Нет данных за {{ $selectedYear }} год
            </div>
        </x-filament::section>
    @endif
</x-filament::section>