<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class SupportTicketStatsWidget extends BaseWidget
{
    protected ?string $pollingInterval = '15s';
    
    protected static bool $isLazy = false;

    protected function getStats(): array
    {
        // Total tickets
        $totalTickets = SupportTicket::count();
        
        // Tickets created this month
        $ticketsThisMonth = SupportTicket::whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Tickets by status
        $openTickets = SupportTicket::where('status', 'open')->count();
        // $inProgressTickets = SupportTicket::where('status', 'in_progress')->count();
        // $resolvedTickets = SupportTicket::where('status', 'resolved')->count();
        // $closedTickets = SupportTicket::where('status', 'closed')->count();

        // Tickets by priority
        // $highPriorityTickets = SupportTicket::where('priority', 'high')->count();
        // $mediumPriorityTickets = SupportTicket::where('priority', 'medium')->count();
        // $lowPriorityTickets = SupportTicket::where('priority', 'low')->count();

        // Resolution rate
        // $resolvedThisMonth = SupportTicket::where('status', 'resolved')
        //     ->whereMonth('resolved_at', now()->month)
        //     ->whereYear('resolved_at', now()->year)
        //     ->count();
            
        // $resolutionRate = $ticketsThisMonth > 0 ? ($resolvedThisMonth / $ticketsThisMonth) * 100 : 0;

        // // Average resolution time (in hours)
        // $avgResolutionTime = SupportTicket::whereNotNull('resolved_at')
        //     ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours')
        //     ->first()->avg_hours ?? 0;

        // Unassigned tickets
        $unassignedTickets = SupportTicket::whereNull('assigned_to')
            ->whereIn('status', ['open', 'in_progress'])
            ->count();

        return [
            Stat::make('Open Tickets', $openTickets)
                ->description('Tickets awaiting response')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('danger'),
                
            // Stat::make('In Progress', $inProgressTickets)
            //     ->description('Tickets being worked on')
            //     ->descriptionIcon('heroicon-m-arrow-path')
            //     ->color('warning'),
                
            // Stat::make('High Priority', $highPriorityTickets)
            //     ->description('Urgent tickets')
            //     ->descriptionIcon('heroicon-m-exclamation-triangle')
            //     ->color('danger'),
                
            // Stat::make('Resolution Rate', number_format($resolutionRate, 1) . '%')
            //     ->description('Monthly resolution rate')
            //     ->descriptionIcon('heroicon-m-check-circle')
            //     ->color('success'),
                
            // Stat::make('Avg Resolution Time', number_format($avgResolutionTime, 1) . 'h')
            //     ->description('Average time to resolve')
            //     ->descriptionIcon('heroicon-m-clock')
            //     ->color('info'),
                
            Stat::make('Unassigned Tickets', $unassignedTickets)
                ->description('Tickets needing assignment')
                ->descriptionIcon('heroicon-m-user-minus')
                ->color('warning'),
        ];
    }
}