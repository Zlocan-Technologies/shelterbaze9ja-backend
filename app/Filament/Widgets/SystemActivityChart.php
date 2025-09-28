<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Property;
use App\Models\RentPayment;
use App\Models\SupportTicket;
use App\Models\RentSaving;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class SystemActivityChart extends ChartWidget
{
    protected ?string $heading = 'System Activity Overview (Last 30 Days)';
    
    protected static ?int $sort = 8;
    
    protected int | string | array $columnSpan = 'full';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(function ($daysBack) {
            return now()->subDays($daysBack);
        });

        $userRegistrations = $days->map(function ($day) {
            return User::whereDate('created_at', $day->toDateString())->count();
        })->toArray();

        $propertyListings = $days->map(function ($day) {
            return Property::whereDate('created_at', $day->toDateString())->count();
        })->toArray();

        $rentPayments = $days->map(function ($day) {
            return RentPayment::whereDate('created_at', $day->toDateString())->count();
        })->toArray();

        $supportTickets = $days->map(function ($day) {
            return SupportTicket::whereDate('created_at', $day->toDateString())->count();
        })->toArray();

        $dayLabels = $days->map(function ($day) {
            return $day->format('M d');
        })->toArray();

        return [
            'datasets' => [
                [
                    'label' => 'User Registrations',
                    'data' => $userRegistrations,
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'borderColor' => 'rgba(34, 197, 94, 1)',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Property Listings',
                    'data' => $propertyListings,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => 'rgba(59, 130, 246, 1)',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Rent Payments',
                    'data' => $rentPayments,
                    'backgroundColor' => 'rgba(245, 158, 11, 0.1)',
                    'borderColor' => 'rgba(245, 158, 11, 1)',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                ],
                [
                    'label' => 'Support Tickets',
                    'data' => $supportTickets,
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'borderColor' => 'rgba(239, 68, 68, 1)',
                    'borderWidth' => 2,
                    'fill' => false,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $dayLabels,
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
                    'position' => 'top',
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
            'interaction' => [
                'intersect' => false,
                'mode' => 'index',
            ],
        ];
    }
}