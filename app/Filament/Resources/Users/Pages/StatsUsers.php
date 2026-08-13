<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\Users\Widgets\UsersChartWidget;
use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

class StatsUsers extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.users.pages.stats-users';

    protected static ?string $title = 'Статистика';

    protected static ?string $navigationLabel = 'Статистика';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|null|\UnitEnum $navigationGroup = 'Сайт';

    protected static ?int $navigationSort = 2;

    protected function getHeaderWidgets(): array
    {
        return [
            UsersChartWidget::class,
        ];
    }

    public function table(Table $table): Table
    {
        $yearRows = DB::select("
            SELECT SUBSTR(p.date, 4, 4) AS year
            FROM projects p
            INNER JOIN users_to_projects utp ON utp.project_id = p.id
            WHERE utp.user_id != 1
              AND SUBSTR(p.date, 4, 4) REGEXP '^[0-9]{4}$'
            GROUP BY SUBSTR(p.date, 4, 4)
            ORDER BY SUBSTR(p.date, 4, 4) DESC
        ");

        $years = array_map(fn ($row) => $row->year, $yearRows);

        $data = DB::select("
            SELECT u.id,
                   u.name,
                   u.site,
                   SUBSTR(p.date, 4, 4) AS year,
                   COUNT(*) AS cnt
            FROM users u
            INNER JOIN users_to_projects utp ON utp.user_id = u.id
            INNER JOIN projects p ON p.id = utp.project_id
            WHERE u.id != 1
              AND SUBSTR(p.date, 4, 4) REGEXP '^[0-9]{4}$'
            GROUP BY u.id, u.name, u.site, SUBSTR(p.date, 4, 4)
        ");

        $clients = [];

        foreach ($data as $row) {
            if (! isset($clients[$row->id])) {
                $clients[$row->id] = [
                    '__key' => (string) $row->id,
                    'id' => $row->id,
                    'name' => $row->name,
                    'site' => $row->site,
                    'years' => array_fill_keys($years, 0),
                    'total' => 0,
                ];
            }

            $clients[$row->id]['years'][$row->year] = (int) $row->cnt;
            $clients[$row->id]['total'] += (int) $row->cnt;
        }

        usort($clients, fn ($a, $b) => $b['total'] <=> $a['total']);

        $totals = array_fill_keys($years, 0);
        $maxPerYear = array_fill_keys($years, 0);

        foreach ($clients as $client) {
            foreach ($years as $year) {
                $totals[$year] += $client['years'][$year];
                $maxPerYear[$year] = max($maxPerYear[$year], $client['years'][$year]);
            }
        }

        $clients[] = [
            '__key' => 'total',
            'id' => 'total',
            'name' => 'Всего',
            'site' => '',
            'years' => $totals,
            'total' => array_sum($totals),
        ];

        $records = array_values($clients);

        $columns = [
            TextColumn::make('name')
                ->label('Клиент')
                ->formatStateUsing(fn ($state, $record) => $record['__key'] === 'total'
                    ? new HtmlString('<strong style="font-weight:700;">' . e($state) . '</strong>')
                    : $state),
            TextColumn::make('site')
                ->label('Сайт')
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        foreach ($years as $index => $year) {
            $column = TextColumn::make("years.{$year}")
                ->label($year)
                ->alignCenter()
                ->formatStateUsing(fn ($state, $record) => $this->renderYearCell(
                    $count = (int) ($state ?? 0),
                    $record['__key'] === 'total',
                    $maxPerYear[$year],
                    $count > 0 && $count === $maxPerYear[$year],
                ));

            if ($index >= 10) {
                $column->toggleable(isToggledHiddenByDefault: true);
            }

            $columns[] = $column;
        }

        $columns[] = TextColumn::make('total')
            ->label('Всего')
            ->alignCenter()
            ->formatStateUsing(fn ($state, $record) => new HtmlString(
                '<strong style="font-weight:700; color:var(--primary-700);">' . $state . '</strong>'
            ));

        return $table
            ->records(fn () => $records)
            ->columns($columns);
    }

    protected function renderYearCell(int $count, bool $isTotal, int $max, bool $isTop = false): HtmlString
    {
        if ($isTotal) {
            return new HtmlString(
                '<strong style="font-weight:700; color:var(--primary-700);">' . $count . '</strong>'
            );
        }

        if ($count === 0) {
            return new HtmlString('<span style="color:var(--gray-400);">·</span>');
        }

        $pct = $max > 0 ? (int) round($count / $max * 100) : 0;

        $numberColor = $isTop ? 'var(--success-600)' : 'var(--primary-600)';
        $barColor = $isTop ? 'var(--success-500)' : 'var(--primary-500)';

        return new HtmlString(
            '<div style="display:inline-flex; flex-direction:column; align-items:center; gap:2px; width:60px;">' .
                '<span style="font-weight:600; font-size:12px; color:' . $numberColor . '; line-height:1;">' . $count . '</span>' .
                '<div style="width:100%; height:4px; background:var(--gray-200); border-radius:2px; overflow:hidden;">' .
                    '<div style="width:' . $pct . '%; height:100%; background:' . $barColor . ';"></div>' .
                '</div>' .
            '</div>'
        );
    }
}
