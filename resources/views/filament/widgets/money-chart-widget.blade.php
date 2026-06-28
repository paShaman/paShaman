@assets
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
@endassets

<div x-data="moneyChartWidget">
    <x-filament::section>
        <div class="fi-section-content" style="display: flex; flex-direction: column; gap: 1rem;">
            {{-- Year selector --}}
            <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;">
                <x-filament::section.heading>
                    📈 График site + salary
                </x-filament::section.heading>
                <div style="display: flex; align-items: center; gap: 0.5rem;">
                    <x-filament::input.wrapper>
                        <x-filament::input.select
                            wire:model.live="selectedYear"
                            wire:change="selectYear($event.target.value)"
                        >
                            @foreach ($availableYears as $year)
                                <option value="{{ $year }}" @selected($selectedYear === $year)>
                                    {{ $year }}
                                </option>
                            @endforeach
                        </x-filament::input.select>
                    </x-filament::input.wrapper>
                </div>
            </div>

            {{-- Chart --}}
            <div style="position: relative; height: 320px;">
                <canvas x-ref="chartCanvas"></canvas>
            </div>
        </div>
    </x-filament::section>
</div>

@push('scripts')
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('moneyChartWidget', () => ({
            chart: null,

            init() {
                this.$watch('$wire.chartMonths', () => this.renderChart());
                this.$watch('$wire.chartDataSite', () => this.renderChart());
                this.$watch('$wire.chartDataSalary', () => this.renderChart());
                this.$nextTick(() => this.renderChart());
            },

            renderChart() {
                const canvas = this.$refs.chartCanvas;
                if (!canvas) return;
                if (typeof Chart === 'undefined') return;

                if (this.chart) {
                    this.chart.destroy();
                    this.chart = null;
                }

                const months = this.$wire?.chartMonths ?? [];
                const siteData = this.$wire?.chartDataSite ?? [];
                const salaryData = this.$wire?.chartDataSalary ?? [];

                if (!months.length) return;

                this.chart = new Chart(canvas, {
                    type: 'bar',
                    data: {
                        labels: months,
                        datasets: [
                            {
                                label: 'Site',
                                data: siteData,
                                backgroundColor: 'rgba(59,130,246,0.75)',
                                borderColor: '#3b82f6',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Salary',
                                data: salaryData,
                                backgroundColor: 'rgba(34,197,94,0.75)',
                                borderColor: '#22c55e',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                        ],
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: {
                                    padding: 16,
                                    usePointStyle: true,
                                },
                            },
                            tooltip: {
                                callbacks: {
                                    label: (ctx) => {
                                        return ctx.dataset.label + ': ' + ctx.raw.toLocaleString('ru-RU') + ' ₽';
                                    },
                                },
                            },
                        },
                        scales: {
                            x: {
                                stacked: false,
                                grid: { display: false },
                            },
                            y: {
                                stacked: false,
                                beginAtZero: true,
                                ticks: {
                                    callback: (v) => (v / 1000).toFixed(0) + 'k ₽',
                                },
                            },
                        },
                    },
                });
            },
        }));
    });
</script>
@endpush