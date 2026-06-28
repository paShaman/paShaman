<?php

namespace App\Filament\Resources\Money\Pages;

use App\Filament\Resources\Money\MoneyResource;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class StatsMoney extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = MoneyResource::class;

    protected string $view = 'filament.resources.money.pages.stats-money';

    protected static ?string $title = 'Статистика';

    protected static ?string $navigationLabel = 'Статистика';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|null|\UnitEnum $navigationGroup = 'Деньги';

    protected static ?int $navigationSort = 2;

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

    public function table(Table $table): Table
    {
        $rows = DB::select("
            SELECT *
            FROM (
                SELECT t.*,
                       ((t.per_month_work / LAG(t.per_month_work) OVER (ORDER BY t.year) - 1) * 100) AS percent
                FROM ( 
                   SELECT m.year,
                          SUM(m.sum) AS per_year,
                          round(SUM(CASE WHEN TYPE IN ('site', 'salary') THEN m.sum ELSE 0 END) / COUNT(DISTINCT m.month)) AS per_month_work,
                          round(SUM(m.sum) / COUNT(DISTINCT m.month)) AS per_month_all,
                          SUM(CASE WHEN m.type = 'delta' THEN m.sum ELSE 0 END) AS delta,
                          SUM(CASE WHEN m.date_payed IS NULL AND m.status != 'plan' THEN m.sum ELSE 0 END) AS not_payed,
                          SUM(CASE WHEN m.date_payed IS NULL THEN m.sum ELSE 0 END) AS not_payed_plan
                   FROM money m
                   WHERE 1=1
                     AND m.status NOT IN ('cancel')
                   GROUP BY m.year
                ) t
            ) t2
            ORDER BY year DESC
        ");

        $records = collect($rows)->map(function ($row, $index) {
            return [
                'id' => $index + 1,
                'year' => $row->year,
                'per_year' => $row->per_year,
                'per_month_work' => $row->per_month_work,
                'per_month_all' => $row->per_month_all,
                'delta' => $row->delta,
                'not_payed' => $row->not_payed,
                'not_payed_plan' => $row->not_payed_plan,
                'percent' => $row->percent,
            ];
        })->toArray();

        return $table
            ->records(fn () => $records)
            ->columns([
                TextColumn::make('year')
                    ->label('Год'),
                TextColumn::make('per_year')
                    ->label('Общий доход')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB'),
                TextColumn::make('per_month_work')
                    ->label('Работа в мес.')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB'),
                TextColumn::make('per_month_all')
                    ->label('Всего в мес.')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB'),
                TextColumn::make('delta')
                    ->label('Delta')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB')
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger'),
                TextColumn::make('not_payed')
                    ->label('Не оплачено')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB')
                    ->color('warning'),
                TextColumn::make('not_payed_plan')
                    ->label('Не оплачено (+план)')
                    ->numeric(decimalPlaces: 0)
                    ->money('RUB')
                    ->color('gray'),
                TextColumn::make('percent')
                    ->label('Рост раб., %')
                    ->numeric(decimalPlaces: 1)
                    ->suffix('%')
                    ->color(fn ($state) => $state === null ? 'gray' : ($state >= 0 ? 'success' : 'danger')),
            ]);
    }
}