<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\SystemOverviewWidget;
use App\Filament\Widgets\UserManagementStatsWidget;
use App\Filament\Widgets\PropertyManagementStatsWidget;
use App\Filament\Widgets\RentManagementStatsWidget;
use App\Filament\Widgets\SupportTicketStatsWidget;
use App\Filament\Widgets\MonthlyRevenueChart;
use App\Filament\Widgets\UserGrowthChart;
use App\Filament\Widgets\PropertyStatusChart;
use App\Filament\Widgets\PaymentStatusChart;
use App\Filament\Widgets\UserRoleDistributionChart;
use App\Filament\Widgets\SupportTicketTypesChart;
use App\Filament\Widgets\SystemActivityChart;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;
use BackedEnum;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHome;

    public function getWidgets(): array
    {
        return [
            SystemOverviewWidget::class,
            // UserManagementStatsWidget::class,
            // PropertyManagementStatsWidget::class,
            // RentManagementStatsWidget::class,
            SupportTicketStatsWidget::class,
            SystemActivityChart::class,
            MonthlyRevenueChart::class,
            UserGrowthChart::class,
            // PropertyStatusChart::class,
            // PaymentStatusChart::class,
            // UserRoleDistributionChart::class,
            // SupportTicketTypesChart::class,
        ];
    }
}
