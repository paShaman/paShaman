<x-filament::section :heading="'📊 Заработок за ' . $selectedYear . ' год'">
    @if ($stats)
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem;">

            {{-- Общий доход --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #eff6ff, #eef2ff); border-radius: 8px; border-left: 4px solid #3b82f6;">
                <div style="font-size: 14px; font-weight: 500; color: #3b82f6;">💰 Общий доход</div>
                <div style="font-size: 22px; font-weight: 700; color: #1d4ed8; margin: 0.25rem 0;">
                    {{ number_format($stats['per_year'], 0, ',', ' ') }} ₽
                </div>
            </div>

            {{-- Работа в месяц --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border-radius: 8px; border-left: 4px solid #10b981;">
                <div style="font-size: 14px; font-weight: 500; color: #10b981;">💼 Работа в месяц</div>
                <div style="font-size: 22px; font-weight: 700; color: #047857; margin: 0.25rem 0;">
                    {{ number_format($stats['per_month_work'], 0, ',', ' ') }} ₽
                </div>
            </div>

            {{-- Всего в месяц --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #f5f3ff, #faf5ff); border-radius: 8px; border-left: 4px solid #8b5cf6;">
                <div style="font-size: 14px; font-weight: 500; color: #8b5cf6;">💵 Всего в месяц</div>
                <div style="font-size: 22px; font-weight: 700; color: #6d28d9; margin: 0.25rem 0;">
                    {{ number_format($stats['per_month_all'], 0, ',', ' ') }} ₽
                </div>
            </div>

            {{-- Рост раб. --}}
            @if (($stats['pct']['per_month_work'] ?? null) !== null)
                @php
                    $pct = $stats['pct']['per_month_work'];
                    $isPositive = $pct >= 0;
                @endphp
                <div style="padding: 1rem; background: linear-gradient(135deg, {{ $isPositive ? '#f0fdf4, #ecfdf5' : '#fef2f2, #fff1f2' }}); border-radius: 8px; border-left: 4px solid {{ $isPositive ? '#22c55e' : '#ef4444' }};">
                    <div style="font-size: 14px; font-weight: 500; color: {{ $isPositive ? '#16a34a' : '#dc2626' }};">📈 Рост раб.</div>
                    <div style="font-size: 22px; font-weight: 700; color: {{ $isPositive ? '#16a34a' : '#dc2626' }}; margin: 0.25rem 0;">
                        {{ $isPositive ? '↑' : '↓' }} {{ abs($pct) }}%
                    </div>
                </div>
            @endif

            {{-- Не оплачено --}}
            <div style="padding: 1rem; background: linear-gradient(135deg, #fffbeb, #fefce8); border-radius: 8px; border-left: 4px solid #f59e0b;">
                <div style="font-size: 14px; font-weight: 500; color: #d97706;">⏳ Не оплачено</div>
                <div style="font-size: 22px; font-weight: 700; color: #b45309; margin: 0.25rem 0;">
                    {{ number_format($stats['not_payed'], 0, ',', ' ') }} ₽
                </div>
            </div>

        </div>
    @else
        <div style="text-align: center; color: var(--gray-500); padding: 1rem 0;">
            Нет данных за {{ $selectedYear }} год
        </div>
    @endif
</x-filament::section>