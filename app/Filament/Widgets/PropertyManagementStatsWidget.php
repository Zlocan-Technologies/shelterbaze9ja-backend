<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PropertyManagementStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Total properties
        $totalProperties = Property::count();
        
        // Properties added this month
        $propertiesThisMonth = Property::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();
            
        // Properties added last month
        $propertiesLastMonth = Property::whereMonth('created_at', now()->subMonth()->month)
            ->whereYear('created_at', now()->subMonth()->year)
            ->count();
            
        // Calculate growth percentage
        $propertyGrowth = $propertiesLastMonth > 0 
            ? (($propertiesThisMonth - $propertiesLastMonth) / $propertiesLastMonth) * 100 
            : ($propertiesThisMonth > 0 ? 100 : 0);

        // Properties by status
        $availableProperties = Property::where('status', 'available')->count();
        $occupiedProperties = Property::where('status', 'occupied')->count();
        $maintenanceProperties = Property::where('status', 'maintenance')->count();

        // Properties by type
        $apartments = Property::where('property_type', 'apartment')->count();
        $houses = Property::where('property_type', 'house')->count();
        $commercialProperties = Property::where('property_type', 'commercial')->count();

        // Average rent calculation
        $averageRent = Property::where('status', 'available')->avg('rent_amount') ?? 0;

        return [
            Stat::make('Total Properties', $totalProperties)
                ->description('All listed properties')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),
                
            Stat::make('New Properties', $propertiesThisMonth)
                ->description(sprintf('%.1f%% from last month', $propertyGrowth))
                ->descriptionIcon($propertyGrowth >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($propertyGrowth >= 0 ? 'success' : 'danger'),
                
            Stat::make('Available Properties', $availableProperties)
                ->description('Ready for rent')
                ->descriptionIcon('heroicon-m-key')
                ->color('success'),
                
            Stat::make('Occupied Properties', $occupiedProperties)
                ->description('Currently rented')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('info'),
                
            Stat::make('Under Maintenance', $maintenanceProperties)
                ->description('Properties in maintenance')
                ->descriptionIcon('heroicon-m-wrench-screwdriver')
                ->color('warning'),
                
            Stat::make('Average Rent', '₦' . number_format($averageRent, 0))
                ->description('Average monthly rent')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('success'),
        ];
    }
}