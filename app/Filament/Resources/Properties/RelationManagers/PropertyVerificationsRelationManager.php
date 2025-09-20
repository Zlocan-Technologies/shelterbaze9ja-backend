<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Models\PropertyVerification;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Schemas\Components\Section;
use Illuminate\Support\HtmlString;

class PropertyVerificationsRelationManager extends RelationManager
{
    protected static string $relationship = 'verifications';

    protected static ?string $title = 'Property Verifications';

    protected static ?string $modelLabel = 'Verification';

    protected static ?string $pluralModelLabel = 'Verifications';

    public function form(Schema $schema): Schema
    {
        // Disable form creation/editing
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                // TextColumn::make('id')
                //     ->label('Verification ID')
                //     ->sortable()
                //     ->searchable(),
                
                TextColumn::make('agent.name')
                    ->label('Verified By')
                    ->sortable()
                    ->searchable()
                    ->default('Unknown Agent'),
                
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'success' => PropertyVerification::STATUS_VERIFIED,
                        'danger' => PropertyVerification::STATUS_REJECTED,
                        'warning' => 'pending',
                    ])
                    ->icons([
                        'heroicon-o-check-circle' => PropertyVerification::STATUS_VERIFIED,
                        'heroicon-o-x-circle' => PropertyVerification::STATUS_REJECTED,
                        'heroicon-o-clock' => 'pending',
                    ]),
                
                TextColumn::make('verification_date')
                    ->label('Verification Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
                
                TextColumn::make('verification_notes')
                    ->label('Notes')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    }),
                
                TextColumn::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->limit(50)
                    ->tooltip(function (TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 50 ? $state : null;
                    })
                    ->visible(fn ($record) => $record?->status === PropertyVerification::STATUS_REJECTED),
                        
                TextColumn::make('coordinates')
                    ->label('Location')
                    ->getStateUsing(function ($record) {
                        if ($record->latitude && $record->longitude) {
                            return number_format($record->latitude, 6) . ', ' . number_format($record->longitude, 6);
                        }
                        return 'No location';
                    })
                    ->copyable()
                    ->copyMessage('Coordinates copied')
                    ->tooltip('Click to copy coordinates'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        PropertyVerification::STATUS_VERIFIED => 'Verified',
                        PropertyVerification::STATUS_REJECTED => 'Rejected',
                        'pending' => 'Pending',
                    ]),
            ])
            ->headerActions([
                // Disable creation
            ])
            ->actions([
                Action::make('images')
                    ->label('images')
                    ->icon('heroicon-o-eye')
                    ->form([
                        Section::make()
                            ->columns([
                                'sm' => 2,
                                'xl' => 2,
                                '2xl' => 2,
                            ])->schema([

                                Placeholder::make('Images')
                                    ->label('')
                                    ->content(function ($record): HtmlString {
                                        $imgDiv = "";
                                        foreach ($record->verification_images as $image) {
                                            $url = $image['url'];
                                            $imgDiv .= "<a href='{$url}' target='_blank'><img class='w-[100px] h-auto rounded-lg shadow-sm hover:shadow-md transition-shadow' src='" . $url . "'></a>";
                                        }

                                        return new HtmlString("<div class='flex gap-3 items-center justify-start flex-wrap'>" . $imgDiv . "</div>");
                                    }),
                            ]),
                    ])
                    ,
            ])
            ->bulkActions([
                // Disable bulk actions including delete
            ])
            ->defaultSort('verification_date', 'desc')
            ->emptyStateHeading('No Verifications')
            ->emptyStateDescription('This property has not been verified yet.')
            ->emptyStateIcon('heroicon-o-shield-exclamation');
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}