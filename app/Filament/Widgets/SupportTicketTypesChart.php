<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SupportTicketTypesChart extends ChartWidget
{
    // protected static ?string $heading = 'Support Ticket Types Distribution';
    
    protected static ?int $sort = 7;

    protected function getData(): array
    {
        $ticketData = SupportTicket::select('ticket_type', DB::raw('count(*) as count'))
            ->groupBy('ticket_type')
            ->pluck('count', 'ticket_type')
            ->toArray();

        $labels = array_keys($ticketData);
        $data = array_values($ticketData);

        $colors = [
            'general' => 'rgba(156, 163, 175, 0.8)',
            'property_issue' => 'rgba(245, 158, 11, 0.8)',
            'payment_issue' => 'rgba(239, 68, 68, 0.8)',
            'technical' => 'rgba(59, 130, 246, 0.8)',
            'account_issue' => 'rgba(34, 197, 94, 0.8)',
        ];

        $backgroundColors = array_map(function ($type) use ($colors) {
            return $colors[$type] ?? 'rgba(156, 163, 175, 0.8)';
        }, $labels);

        // Format labels for display
        $formattedLabels = array_map(function($label) {
            return ucwords(str_replace('_', ' ', $label));
        }, $labels);

        return [
            'datasets' => [
                [
                    'label' => 'Tickets',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                    'borderColor' => array_map(function ($color) {
                        return str_replace('0.8', '1', $color);
                    }, $backgroundColors),
                    'borderWidth' => 2,
                ],
            ],
            'labels' => $formattedLabels,
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
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
            'maintainAspectRatio' => false,
        ];
    }
}