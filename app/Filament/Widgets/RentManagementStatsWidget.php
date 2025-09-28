<?php

namespace App\Filament\Widgets;

use App\Models\RentPayment;
use App\Models\RentSaving;
use App\Models\SavingsTransaction;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class RentManagementStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Total rent payments
        $totalPayments = RentPayment::count();
        
        // Revenue this month
        $revenueThisMonth = RentPayment::where('status', 'verified')
            ->whereMonth('payment_date', now()->month)
            ->whereYear('payment_date', now()->year)
            ->sum('amount');
            
        // Revenue last month
        $revenueLastMonth = RentPayment::where('status', 'verified')
            ->whereMonth('payment_date', now()->subMonth()->month)
            ->whereYear('payment_date', now()->subMonth()->year)
            ->sum('amount');
            
        // Calculate revenue growth
        $revenueGrowth = $revenueLastMonth > 0 
            ? (($revenueThisMonth - $revenueLastMonth) / $revenueLastMonth) * 100 
            : ($revenueThisMonth > 0 ? 100 : 0);

        // Payment status stats
        $pendingPayments = RentPayment::where('status', 'pending')->count();
        $verifiedPayments = RentPayment::where('status', 'verified')->count();
        $rejectedPayments = RentPayment::where('status', 'rejected')->count();

        // Overdue payments
        $overduePayments = RentPayment::where('due_date', '<', now())
            ->where('status', '!=', 'verified')
            ->count();

        // Savings statistics
        $totalSavings = RentSaving::sum('current_amount');
        $activeSavingPlans = RentSaving::where('status', 'active')->count();

        // Total savings transactions this month
        $savingsThisMonth = SavingsTransaction::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('amount');

        return [
            Stat::make('Monthly Revenue', '₦' . number_format($revenueThisMonth, 2))
                ->description(sprintf('%.1f%% from last month', $revenueGrowth))
                ->descriptionIcon($revenueGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($revenueGrowth >= 0 ? 'success' : 'danger'),
                
            Stat::make('Total Payments', $totalPayments)
                ->description('All rent payments')
                ->descriptionIcon('heroicon-m-credit-card')
                ->color('primary'),
                
            Stat::make('Pending Payments', $pendingPayments)
                ->description('Awaiting verification')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
                
            Stat::make('Overdue Payments', $overduePayments)
                ->description('Past due date')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),
                
            Stat::make('Total Savings', '₦' . number_format($totalSavings, 2))
                ->description($activeSavingPlans . ' active savings plans')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('info'),
                
            Stat::make('Savings This Month', '₦' . number_format($savingsThisMonth, 2))
                ->description('Monthly savings activity')
                ->descriptionIcon('heroicon-m-arrow-up-circle')
                ->color('success'),
        ];
    }
}