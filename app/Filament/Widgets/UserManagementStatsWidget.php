<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UserManagementStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Total users count
        $totalUsers = User::count();
        
        // Users registered this month
        $usersThisMonth = User::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        // Users registered last month
        $usersLastMonth = User::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
            
        // Calculate growth percentage
        $userGrowth = $usersLastMonth > 0 
            ? (($usersThisMonth - $usersLastMonth) / $usersLastMonth) * 100 
            : ($usersThisMonth > 0 ? 100 : 0);

        // Active users (users with completed profiles and active status)
        $activeUsers = User::where('account_status', 'active')->count();
        
        // Users by role
        $customers = User::where('role', 'user')->count();
        $landlords = User::where('role', 'landlord')->count();
        $agents = User::where('role', 'agent')->count();
        $admins = User::where('role', 'admin')->count();

        // Verified vs unverified
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        $unverifiedUsers = User::whereNull('email_verified_at')->count();

        return [
            Stat::make('Total Users', $totalUsers)
                ->description('All registered users')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('New Users This Month', $usersThisMonth)
                ->description(sprintf('%.1f%% from last month', $userGrowth))
                ->descriptionIcon($userGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($userGrowth >= 0 ? 'success' : 'danger'),
                
            Stat::make('Active Users', $activeUsers)
                ->description('Users with active status')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('info'),
                
            Stat::make('Users', $customers)
                ->description('Regular user accounts')
                ->descriptionIcon('heroicon-m-home')
                ->color('success'),
                
            Stat::make('Landlords', $landlords)
                ->description('Property owners')
                ->descriptionIcon('heroicon-m-building-office')
                ->color('warning'),
                
            Stat::make('Verified Users', $verifiedUsers)
                ->description(sprintf('%.1f%% verification rate', $totalUsers > 0 ? ($verifiedUsers / $totalUsers) * 100 : 0))
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
        ];
    }
}