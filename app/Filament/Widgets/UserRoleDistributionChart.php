<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class UserRoleDistributionChart extends ChartWidget
{
    // protected static ?string $heading = 'User Role Distribution';
    
    protected static ?int $sort = 6;

    protected function getData(): array
    {
        $roleData = User::select('role', DB::raw('count(*) as count'))
            ->groupBy('role')
            ->pluck('count', 'role')
            ->toArray();

        $labels = array_keys($roleData);
        $data = array_values($roleData);

        $colors = [
            'user' => 'rgba(34, 197, 94, 0.8)',
            'landlord' => 'rgba(245, 158, 11, 0.8)',
            'agent' => 'rgba(59, 130, 246, 0.8)',
            'admin' => 'rgba(239, 68, 68, 0.8)',
        ];

        $backgroundColors = array_map(function ($role) use ($colors) {
            return $colors[$role] ?? 'rgba(156, 163, 175, 0.8)';
        }, $labels);

        return [
            'datasets' => [
                [
                    'label' => 'Users',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => array_map(function ($color) {
                        return str_replace('0.8', '1', $color);
                    }, $backgroundColors),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => array_map('ucfirst', $labels),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}