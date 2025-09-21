<?php

namespace App\Filament\Resources\Properties\Pages;

use App\Filament\Resources\Properties\PropertyResource;
use App\Filament\Resources\Properties\RelationManagers\PropertyVerificationsRelationManager;
use App\Filament\Resources\Properties\RelationManagers\MediaRelationManager;
use App\Models\PropertyMedia;
use App\Services\FileUploadService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getRelationManagers(): array
    {
        // Only show MediaRelationManager on edit page, hide PropertyVerificationsRelationManager
        return [
            MediaRelationManager::class,
        ];
    }

    protected function afterSave(): void
    {
        $property = $this->record;
        $fileUploadService = new FileUploadService();

        // Handle property images
        if (isset($this->data['property_images']) && !empty($this->data['property_images'])) {
            $this->processMediaFiles($this->data['property_images'], 'image', $property, $fileUploadService);
        }

        // Handle property videos
        if (isset($this->data['property_videos']) && !empty($this->data['property_videos'])) {
            $this->processMediaFiles($this->data['property_videos'], 'video', $property, $fileUploadService);
        }
    }

    private function processMediaFiles(array $files, string $mediaType, $property, FileUploadService $fileUploadService): void
    {
        // Check if property already has a primary image
        $hasPrimaryImage = $property->media()->where('media_type', 'image')->where('is_primary', true)->exists();

        foreach ($files as $uuid => $filePath) {
            try {
                // Get the temporary file path
                $tempFilePath = Storage::disk('local')->path($filePath);
                
                if (file_exists($tempFilePath)) {
                    // Create UploadedFile instance
                    $uploadedFile = new UploadedFile(
                        $tempFilePath,
                        basename($filePath),
                        mime_content_type($tempFilePath),
                        null,
                        true
                    );
                    
                    // Upload to Cloudinary
                    $folderName = $mediaType === 'image' ? 'properties/images' : 'properties/videos';
                    $uploadResult = $fileUploadService->uploadToCloudinary($uploadedFile, $folderName);
                    
                    // Create PropertyMedia record
                    PropertyMedia::create([
                        'property_id' => $property->id,
                        'media_type' => $mediaType,
                        'media_url' => $uploadResult['url'],
                        'public_id' => $uploadResult['public_id'] ?? null,
                        'is_primary' => $mediaType === 'image' && !$hasPrimaryImage, // First new image becomes primary if none exists
                    ]);

                    // After setting one image as primary, don't set others
                    if ($mediaType === 'image' && !$hasPrimaryImage) {
                        $hasPrimaryImage = true;
                    }
                    
                    // Clean up temp file
                    Storage::disk('local')->delete($filePath);
                }
            } catch (\Exception $e) {
                Log::error('Property media upload error: ' . $e->getMessage());
                // Continue with other files if one fails
                continue;
            }
        }
    }
}
