<?php

namespace App\Filament\Widgets;

use App\Models\RentPayment;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class PaymentStatusChart extends ChartWidget
{
    // protected static ?string $heading = 'Payment Status Overview';
    
    protected static ?int $sort = 5;

    protected function getData(): array
    {
        $statusData = RentPayment::select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $labels = array_keys($statusData);
        $data = array_values($statusData);

        $colors = [
            'pending' => 'rgba(245, 158, 11, 0.8)',
            'verified' => 'rgba(34, 197, 94, 0.8)',
            'rejected' => 'rgba(239, 68, 68, 0.8)',
            'processing' => 'rgba(59, 130, 246, 0.8)',
        ];

        $backgroundColors = array_map(function ($status) use ($colors) {
            return $colors[$status] ?? 'rgba(156, 163, 175, 0.8)';
        }, $labels);

        return [
            'datasets' => [
                [
                    'label' => 'Payments',
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
        return 'pie';
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