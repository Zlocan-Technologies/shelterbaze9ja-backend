<?php

namespace App\Filament\Resources\Properties\RelationManagers;

use App\Filament\Resources\Properties\PropertyResource;
use App\Http\Requests\Property\UploadMediaRequest;
use App\Models\PropertyMedia;
use App\Services\FileUploadService;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

class MediaRelationManager extends RelationManager
{
    protected static string $relationship = 'media';
    protected static ?string $title = "Media";

    // protected static ?string $relatedResource = PropertyResource::class;



    public function table(Table $table): Table
    {
        return $table
            ->headerActions([

                CreateAction::make()
                    ->label('Add Media')
                    ->form([
                        FileUpload::make('media_url')
                            ->label('Upload Image or Video')
                            ->directory('property-media')
                            ->disk('public')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['image/*', 'video/mp4'])
                            ->required(),
                    ])
                    ->action(function (array $data) {
                        $property = $this->ownerRecord;

                        $relativePath = $data['media_url']; // E.g. 'property-media/photo.jpg'
                        $fullPath = storage_path('app/public/' . $relativePath);

                        // Detect media type
                        $extension = Str::lower(pathinfo($relativePath, PATHINFO_EXTENSION));
                        $mediaType = in_array($extension, ['mp4', 'mov', 'avi']) ? 'video' : 'image';

                        if (!file_exists($fullPath)) {
                            Log::error('File not found at path: ' . $fullPath);
                            throw new \Exception('File not found: ' . $fullPath);
                        }

                        $uploadedFile = new UploadedFile(
                            $fullPath,
                            basename($fullPath),
                            mime_content_type($fullPath),
                            test: true // important: avoids requiring real uploaded file
                        );

                        $fileUploadService = new FileUploadService();
                        $upload = $fileUploadService->uploadToCloudinary($uploadedFile); // expects UploadedFile

                        PropertyMedia::create([
                            'property_id' => $property->id,
                            'media_type' => $mediaType,
                            'media_url' => $upload['url'],
                            'public_id' => $upload['public_id'] ?? null,
                            'is_primary' => true
                        ]);
                    }),

            ])
            ->columns([

                ViewColumn::make('media_preview')
                    ->label('Media Preview')
                    ->view('filament.columns.media-preview'),

                TextColumn::make('media_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'image' => 'success',
                        'video' => 'info',
                        default => 'gray',
                    }),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }

    public function getTableQuery(): Builder
    {
        return $this->getRelationship()->getQuery()->withoutGlobalScopes([
            SoftDeletingScope::class,
        ]);
    }
}
