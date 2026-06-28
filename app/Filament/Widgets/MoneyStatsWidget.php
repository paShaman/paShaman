<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

class MoneyStatsWidget extends Widget
{
    protected static ?int $sort = 1;

    protected string $view = 'filament.widgets.money-stats-widget';

    public ?array $latestYear = null;

    public function mount(): void
    {
        $latest = DB::selectOne("
            SELECT m.year,
                   SUM(m.sum) AS per_year,
                   round(SUM(CASE WHEN TYPE IN ('site', 'salary') THEN m.sum ELSE 0 END) / COUNT(DISTINCT m.month)) AS per_month_work,
                   round(SUM(m.sum) / COUNT(DISTINCT m.month)) AS per_month_all,
                   SUM(CASE WHEN m.type = 'delta' THEN m.sum ELSE 0 END) AS delta,
                   SUM(CASE WHEN m.date_payed IS NULL AND m.status != 'plan' THEN m.sum ELSE 0 END) AS not_payed,
                   SUM(CASE WHEN m.date_payed IS NULL THEN m.sum ELSE 0 END) AS not_payed_plan
            FROM money m
            WHERE m.status NOT IN ('cancel')
              AND m.year = (SELECT MAX(year) FROM money WHERE status NOT IN ('cancel'))
            GROUP BY m.year
        ");

        if ($latest) {
            $this->latestYear = [
                'year' => $latest->year,
                'per_year' => $latest->per_year,
                'per_month_work' => $latest->per_month_work,
                'per_month_all' => $latest->per_month_all,
                'delta' => $latest->delta,
                'not_payed' => $latest->not_payed,
                'not_payed_plan' => $latest->not_payed_plan,
            ];
        }
    }

    public static function canView(): bool
    {
        return true;
    }
}