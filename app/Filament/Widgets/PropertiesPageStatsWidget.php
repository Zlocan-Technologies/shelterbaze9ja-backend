<?php

namespace App\Filament\Widgets;

use App\Models\Property;
use App\Models\RentalAgreement;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class PropertiesPageStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '30s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Property statistics
        $totalProperties = Property::count();
        $availableProperties = Property::where('status', 'available')->count();
        $occupiedProperties = Property::where('status', 'occupied')->count();
        $maintenanceProperties = Property::where('status', 'maintenance')->count();
        
        // Property types
        $apartments = Property::where('property_type', 'apartment')->count();
        $houses = Property::where('property_type', 'house')->count();
        
        // Financial data
        $averageRent = Property::where('status', 'available')->avg('rent_amount') ?? 0;
        $totalRentValue = Property::where('status', 'available')->sum('rent_amount');
        
        // Recent additions
        $todayProperties = Property::whereDate('created_at', today())->count();
        $weekProperties = Property::whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count();

        return [
            Stat::make('Total Properties', number_format($totalProperties))
                ->description($todayProperties . ' added today')
                ->descriptionIcon('heroicon-m-building-office-2')
                ->color('primary'),
                
            Stat::make('Available Properties', number_format($availableProperties))
                ->description('Ready for rent')
                ->descriptionIcon('heroicon-m-key')
                ->color('success'),
                
            Stat::make('Occupied Properties', number_format($occupiedProperties))
                ->description(number_format(($occupiedProperties / max($totalProperties, 1)) * 100, 1) . '% occupancy rate')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('info'),
                
            Stat::make('Average Rent', '₦' . number_format($averageRent, 0))
                ->description('Per month')
                ->descriptionIcon('heroicon-m-currency-dollar')
                ->color('warning'),
        ];
    }
}