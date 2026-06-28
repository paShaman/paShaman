<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MoneyChartWidget extends ChartWidget
{
    protected ?string $heading = '📈 Доход';

    protected static ?int $sort = 2;

    public ?string $filter = null;

    // Меняем тип графика на линейный
    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): array
    {
        $years = DB::select("
            SELECT DISTINCT year
            FROM money
            WHERE status NOT IN ('cancel')
            ORDER BY year DESC
        ");

        $availableYears = array_map(fn ($y) => (string) $y->year, $years);

        if (! $this->filter && ! empty($availableYears)) {
            $this->filter = $availableYears[0];
        }

        return array_combine($availableYears, $availableYears) ?: [];
    }

    protected function getData(): array
    {
        $year = $this->filter;

        if (! $year) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $monthsData = DB::select("
            SELECT m.month,
                   SUM(CASE WHEN m.type = 'site' THEN m.sum ELSE 0 END) AS site_sum,
                   SUM(CASE WHEN m.type = 'salary' THEN m.sum ELSE 0 END) AS salary_sum
            FROM money m
            WHERE m.status NOT IN ('cancel')
              AND m.year = ?
            GROUP BY m.month
            ORDER BY m.month
        ", [$year]);

        // Создаем один массив для общей суммы (Site + Salary) на 12 месяцев
        $chartDataTotal = array_fill(0, 12, 0);

        foreach ($monthsData as $row) {
            $idx = (int) $row->month - 1;
            if ($idx >= 0 && $idx < 12) {
                // Складываем оба направления в одну точку на графике
                $chartDataTotal[$idx] = (float) ($row->site_sum ?? 0) + (float) ($row->salary_sum ?? 0);
            }
        }

        $monthNames = ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн', 'Июл', 'Авг', 'Сен', 'Окт', 'Ноя', 'Дек'];

        return [
            'datasets' => [
                [
                    'label' => 'Общий доход (Site + Salary)',
                    'data' => $chartDataTotal,
                    'borderColor' => '#3b82f6', // Красивая синяя линия Tailwind
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)', // Легкая заливка под линией
                    'tension' => 0.3, // Сглаживание углов линии, чтобы график был плавным
                    'fill' => true, // Включаем заливку под линией
                ],
            ],
            'labels' => $monthNames,
        ];
    }
}