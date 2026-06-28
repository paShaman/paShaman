<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On; // <--- Обязательно добавляем импорт атрибута

class MoneyStatsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.money-stats-widget';

    public ?array $stats = null;

    public ?int $selectedYear = null;

    public array $availableYears = [];

    public function mount(): void
    {
        $years = DB::select("
            SELECT DISTINCT year
            FROM money
            WHERE status NOT IN ('cancel')
            ORDER BY year DESC
        ");

        $this->availableYears = array_map(fn ($y) => $y->year, $years);

        $this->selectedYear = $this->availableYears[0] ?? null;

        if ($this->selectedYear) {
            $this->loadYearData($this->selectedYear);
        }
    }

    public function selectYear(int $year): void
    {
        $this->selectedYear = $year;
        $this->loadYearData($year);
    }

    /**
     * Слушатель события от графика. Автоматически поймает изменение года.
     */
    #[On('moneyYearChanged')]
    public function updateYearFromChart(int $year): void
    {
        $this->selectedYear = $year;
        $this->loadYearData($year);
    }

    protected function loadYearData(int $year): void
    {
        $summary = DB::selectOne("
            SELECT m.year,
                   SUM(m.sum) AS per_year,
                   round(SUM(CASE WHEN TYPE IN ('site', 'salary') THEN m.sum ELSE 0 END) / COUNT(DISTINCT m.month)) AS per_month_work,
                   round(SUM(m.sum) / COUNT(DISTINCT m.month)) AS per_month_all,
                   SUM(CASE WHEN m.type = 'delta' THEN m.sum ELSE 0 END) AS delta,
                   SUM(CASE WHEN m.date_payed IS NULL AND m.status != 'plan' THEN m.sum ELSE 0 END) AS not_payed,
                   SUM(CASE WHEN m.date_payed IS NULL THEN m.sum ELSE 0 END) AS not_payed_plan
            FROM money m
            WHERE m.status NOT IN ('cancel')
              AND m.year = ?
            GROUP BY m.year
        ", [$year]);

        // Previous year for percent change
        $prev = DB::selectOne("
            SELECT SUM(m.sum) AS per_year,
                   round(SUM(CASE WHEN TYPE IN ('site', 'salary') THEN m.sum ELSE 0 END) / COUNT(DISTINCT m.month)) AS per_month_work,
                   round(SUM(m.sum) / COUNT(DISTINCT m.month)) AS per_month_all,
                   SUM(CASE WHEN m.type = 'delta' THEN m.sum ELSE 0 END) AS delta,
                   SUM(CASE WHEN m.date_payed IS NULL AND m.status != 'plan' THEN m.sum ELSE 0 END) AS not_payed,
                   SUM(CASE WHEN m.date_payed IS NULL THEN m.sum ELSE 0 END) AS not_payed_plan
            FROM money m
            WHERE m.status NOT IN ('cancel')
              AND m.year = ?
        ", [$year - 1]);

        if ($summary) {
            $this->stats = [
                'year'       => $summary->year,
                'per_year'       => $summary->per_year,
                'per_month_work' => $summary->per_month_work,
                'per_month_all'  => $summary->per_month_all,
                'delta'          => $summary->delta,
                'not_payed'      => $summary->not_payed,
                'not_payed_plan' => $summary->not_payed_plan,
                'pct' => $prev ? [
                    'per_year'       => $prev->per_year != 0       ? round(($summary->per_year       - $prev->per_year)       / abs($prev->per_year)       * 100, 1) : null,
                    'per_month_work' => $prev->per_month_work != 0 ? round(($summary->per_month_work - $prev->per_month_work) / abs($prev->per_month_work) * 100, 1) : null,
                    'per_month_all'  => $prev->per_month_all != 0  ? round(($summary->per_month_all  - $prev->per_month_all)  / abs($prev->per_month_all)  * 100, 1) : null,
                    'delta'          => $prev->delta != 0          ? round(($summary->delta          - $prev->delta)          / abs($prev->delta)          * 100, 1) : null,
                    'not_payed'      => $prev->not_payed != 0      ? round(($summary->not_payed      - $prev->not_payed)      / abs($prev->not_payed)      * 100, 1) : null,
                    'not_payed_plan' => $prev->not_payed_plan != 0 ? round(($summary->not_payed_plan - $prev->not_payed_plan) / abs($prev->not_payed_plan) * 100, 1) : null,
                ] : [],
            ];
        } else {
            $this->stats = null;
        }
    }

    public static function canView(): bool
    {
        return true;
    }
}