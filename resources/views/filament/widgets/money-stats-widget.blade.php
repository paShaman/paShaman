@if ($latestYear)
    <div class="fi-sc  fi-sc-has-gap fi-grid lg:fi-grid-cols" style="--cols-lg: repeat(2, minmax(0, 1fr)); --cols-default: repeat(1, minmax(0, 1fr));">
        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, #eff6ff, #eef2ff); border-left: 4px solid #3b82f6; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: #2563eb; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">💰</span>
                        {{ $latestYear['year'] }} — Общий доход
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: #1d4ed8;">
                        {{ number_format($latestYear['per_year'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">📊</span>
            </div>
        </x-filament::section>

        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, #ecfdf5, #f0fdf4); border-left: 4px solid #10b981; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: #059669; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">💼</span>
                        Работа в месяц
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: #047857;">
                        {{ number_format($latestYear['per_month_work'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">💼</span>
            </div>
        </x-filament::section>

        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, #f5f3ff, #faf5ff); border-left: 4px solid #8b5cf6; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: #7c3aed; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">💵</span>
                        Всего в месяц
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: #6d28d9;">
                        {{ number_format($latestYear['per_month_all'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">🧮</span>
            </div>
        </x-filament::section>

        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, {{ $latestYear['delta'] >= 0 ? '#f0fdf4, #ecfdf5' : '#fef2f2, #fff1f2' }}); border-left: 4px solid {{ $latestYear['delta'] >= 0 ? '#22c55e' : '#ef4444' }}; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: {{ $latestYear['delta'] >= 0 ? '#16a34a' : '#dc2626' }}; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">↕️</span>
                        Delta
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: {{ $latestYear['delta'] >= 0 ? '#16a34a' : '#dc2626' }};">
                        {{ $latestYear['delta'] >= 0 ? '+' : '' }}{{ number_format($latestYear['delta'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">{{ $latestYear['delta'] >= 0 ? '📈' : '📉' }}</span>
            </div>
        </x-filament::section>

        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, #fffbeb, #fefce8); border-left: 4px solid #f59e0b; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: #d97706; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">⏳</span>
                        Не оплачено
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: #b45309;">
                        {{ number_format($latestYear['not_payed'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">⚠️</span>
            </div>
        </x-filament::section>

        <x-filament::section style="position: relative; overflow: hidden; background: linear-gradient(135deg, #f9fafb, #f8fafc); border-left: 4px solid #9ca3af; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
            <div style="display: flex; align-items: center; justify-content: space-between;">
                <div>
                    <div style="font-size: 14px; font-weight: 500; color: #4b5563; display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 18px;">📅</span>
                        Не оплачено (+план)
                    </div>
                    <div style="font-size: 24px; font-weight: 700; margin-top: 4px; color: #374151;">
                        {{ number_format($latestYear['not_payed_plan'], 0, ',', ' ') }} ₽
                    </div>
                </div>
                <span style="font-size: 32px; opacity: 0.25;">📆</span>
            </div>
        </x-filament::section>
    </div>
@endif