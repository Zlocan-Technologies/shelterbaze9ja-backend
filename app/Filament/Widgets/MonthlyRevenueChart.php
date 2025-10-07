<?php

namespace App\Filament\Widgets;

use App\Models\RentPayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class MonthlyRevenueChart extends ChartWidget
{
    // protected static ?string $heading = 'Monthly Revenue Trend';
    
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $months = collect(range(11, 0))->map(function ($monthsBack) {
            return now()->subMonths($monthsBack);
        });

        $revenueData = $months->map(function ($month) {
            return RentPayment::where('status', 'verified')
                ->whereMonth('payment_date', $month->month)
                ->whereYear('payment_date', $month->year)
                ->sum('amount');
        })->toArray();

        $monthLabels = $months->map(function ($month) {
            return $month->format('M Y');
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'Revenue (₦)',
                    'data' => $revenueData,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
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
                        'callback' => 'function(value) { return "₦" + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}