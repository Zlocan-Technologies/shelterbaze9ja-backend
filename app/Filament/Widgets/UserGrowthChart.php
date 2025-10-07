<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UserGrowthChart extends ChartWidget
{
    // protected static ?string $heading = 'User Registration Growth';
    
    protected static ?int $sort = 3;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(function ($monthsBack) {
            return now()->subMonths($monthsBack);
        });

        $userData = $months->map(function ($month) {
            return User::whereMonth('created_at', $month->month)
                ->whereYear('created_at', $month->year)
                ->count();
        })->toArray();

        $monthLabels = $months->map(function ($month) {
            return $month->format('M Y');
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'New Users',
                    'data' => $userData,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $monthLabels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}