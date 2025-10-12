<?php

namespace App\Filament\Resources\HelpCenterFaqs;

use App\Filament\Resources\HelpCenterFaqs\Pages\CreateHelpCenterFaq;
use App\Filament\Resources\HelpCenterFaqs\Pages\EditHelpCenterFaq;
use App\Filament\Resources\HelpCenterFaqs\Pages\ListHelpCenterFaqs;
use App\Filament\Resources\HelpCenterFaqs\Schemas\HelpCenterFaqForm;
use App\Filament\Resources\HelpCenterFaqs\Tables\HelpCenterFaqsTable;
use App\Models\HelpCenterFaq;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class HelpCenterFaqResource extends Resource
{
    protected static ?string $model = HelpCenterFaq::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Help Center Faq';

    public static function form(Schema $schema): Schema
    {
        return HelpCenterFaqForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return HelpCenterFaqsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHelpCenterFaqs::route('/'),
            'create' => CreateHelpCenterFaq::route('/create'),
            'edit' => EditHelpCenterFaq::route('/{record}/edit'),
        ];
    }
}
