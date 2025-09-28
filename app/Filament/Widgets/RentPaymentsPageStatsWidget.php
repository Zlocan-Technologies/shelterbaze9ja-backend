<?php

namespace App\Filament\Widgets;

use App\Models\RentPayment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RentPaymentsPageStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Payment statistics
        $totalPayments = RentPayment::count();
        $pendingPayments = RentPayment::where('status', 'pending')->count();
        $verifiedPayments = RentPayment::where('status', 'verified')->count();
        $rejectedPayments = RentPayment::where('status', 'rejected')->count();
        
        // Financial data
        $monthlyRevenue = RentPayment::where('status', 'verified')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
            
        $todayPayments = RentPayment::whereDate('created_at', today())->count();
        $todayRevenue = RentPayment::where('status', 'verified')
            ->whereDate('payment_date', today())
            ->sum('amount');
            
        // Overdue payments
        $overduePayments = RentPayment::where('due_date', '<', now())
            ->where('status', '!=', 'verified')
            ->count();

        return [
            Stat::make('Monthly Revenue', '₦' . number_format($monthlyRevenue, 2))
                ->description('₦' . number_format($todayRevenue, 2) . ' today')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
                
            Stat::make('Pending Payments', number_format($pendingPayments))
                ->description('Awaiting verification')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Total Payments', number_format($totalPayments))
                ->description($todayPayments . ' received today')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),
                
            Stat::make('Overdue Payments', number_format($overduePayments))
                ->description('Past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
        ];
    }
}