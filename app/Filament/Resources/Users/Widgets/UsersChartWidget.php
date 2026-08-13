<?php

namespace App\Filament\Resources\Users\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UsersChartWidget extends ChartWidget
{
    protected ?string $heading = '📈 Динамика работы с клиентами';

    protected string $view = 'filament.widgets.users-chart-widget';

    protected int | string | array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                ],
            ],
        ];
    }

    protected function getData(): array
    {
        $rows = DB::select("
            SELECT y.year,
                   y.projects,
                   y.clients
            FROM (
                SELECT SUBSTR(p.date, 4, 4) AS year,
                       COUNT(DISTINCT p.id) AS projects,
                       COUNT(DISTINCT utp.user_id) AS clients
                FROM projects p
                INNER JOIN users_to_projects utp ON utp.project_id = p.id
                WHERE utp.user_id != 1
                  AND SUBSTR(p.date, 4, 4) REGEXP '^[0-9]{4}$'
                GROUP BY SUBSTR(p.date, 4, 4)
            ) y
            ORDER BY y.year
        ");

        $labels = [];
        $projects = [];
        $clients = [];

        foreach ($rows as $row) {
            $labels[] = (string) $row->year;
            $projects[] = (int) $row->projects;
            $clients[] = (int) $row->clients;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Проекты за год',
                    'data' => $projects,
                    'backgroundColor' => '#3b82f6',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Клиентов за год',
                    'type' => 'line',
                    'data' => $clients,
                    'borderColor' => '#10b981',
                    'backgroundColor' => 'rgba(16, 185, 129, 0.1)',
                    'tension' => 0.3,
                    'fill' => true,
                ],
            ],
            'labels' => $labels,
        ];
    }
}
