<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Models\Property;
use App\Models\RentPayment;
use App\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SystemOverviewWidget extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    
    protected static bool $isLazy = false;
    
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        // System-wide metrics
        $totalUsers = User::count();
        $totalProperties = Property::count();
        $totalRevenue = RentPayment::where('status', 'verified')->sum('amount');
        $activeTickets = SupportTicket::whereIn('status', ['open', 'in_progress'])->count();

        // Recent activity
        $newUsersToday = User::whereDate('created_at', today())->count();
        $paymentsToday = RentPayment::whereDate('created_at', today())->count();
        $newTicketsToday = SupportTicket::whereDate('created_at', today())->count();

        // Calculate daily revenue
        $revenueToday = RentPayment::where('status', 'verified')
            ->whereDate('payment_date', today())
            ->sum('amount');

        return [
            Stat::make('Platform Users', number_format($totalUsers))
                ->description($newUsersToday . ' new today')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('Total Properties', number_format($totalProperties))
                ->description('Listed properties')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('success'),
                
            Stat::make('Total Revenue', '₦' . number_format($totalRevenue, 2))
                ->description('₦' . number_format($revenueToday, 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
                
            Stat::make('Active Support Tickets', $activeTickets)
                ->description($newTicketsToday . ' new today')
                ->descriptionIcon('heroicon-m-ticket')
                ->color($activeTickets > 10 ? 'danger' : ($activeTickets > 5 ? 'warning' : 'success')),
        ];
    }
}