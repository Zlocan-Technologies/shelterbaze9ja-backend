<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class UsersPageStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // User statistics
        $totalUsers = User::count();
        $activeUsers = User::where('account_status', 'active')->count();
        $verifiedUsers = User::whereNotNull('email_verified_at')->count();
        
        // Users by role
        $customers = User::where('role', 'user')->count();
        $landlords = User::where('role', 'landlord')->count();
        $agents = User::where('role', 'agent')->count();
        
        // Recent registrations
        $todayUsers = User::whereDate('created_at', today())->count();
        $weekUsers = User::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        return [
            Stat::make('Total Users', number_format($totalUsers))
                ->description($todayUsers . ' registered today')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),
                
            Stat::make('Active Users', number_format($activeUsers))
                ->description(number_format(($activeUsers / max($totalUsers, 1)) * 100, 1) . '% of total')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success'),
                
            Stat::make('Verified Users', number_format($verifiedUsers))
                ->description(number_format(($verifiedUsers / max($totalUsers, 1)) * 100, 1) . '% verification rate')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('info'),
                
            Stat::make('This Week', number_format($weekUsers))
                ->description('New registrations')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('warning'),
        ];
    }
}