<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use App\Models\User;
use App\Services\FileUploadService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class EditAgent extends EditRecord
{
    protected static string $resource = AgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
            Actions\ForceDeleteAction::make(),
            Actions\RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        // Load profile data into the form
        $user = $this->record;
        if ($user->profile) {
            foreach ($user->profile->toArray() as $key => $value) {
                if (!in_array($key, ['id', 'user_id', 'created_at', 'updated_at'])) {
                    // For file uploads, we need to handle Cloudinary URLs differently
                    if (in_array($key, ['nin_selfie_url', 'id_card_url']) && $value && str_starts_with($value, 'http')) {
                        // Store the original URL for display purposes
                        $data["profile.{$key}"] = $value;
                        // Also store a reference for the form to understand it's an existing file
                        $data["profile.{$key}_existing"] = $value;
                    } else {
                        $data["profile.{$key}"] = $value;
                    }
                }
            }
        }
        return $data;
    }

    protected function afterSave(): void
    {
        $profileData = [];
        $user = $this->record;
        $fileUploadService = new FileUploadService();

        // Check if we have profile data in the nested format
        if (isset($this->data['profile']) && is_array($this->data['profile'])) {
            $profileFormData = $this->data['profile'];
            
            foreach ($profileFormData as $key => $value) {
                // Skip the agent_id field as it's auto-generated
                if ($key === 'agent_id') {
                    continue;
                }
                
                // Handle file uploads to Cloudinary
                if (in_array($key, ['nin_selfie_url', 'id_card_url'])) {
                    if ($value !== null && !empty($value)) {
                        // Check if this is file upload data (array with UUID keys)
                        if (is_array($value)) {
                            foreach ($value as $uuid => $filePath) {
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
                                        $folderName = $key === 'nin_selfie_url' ? 'user-profiles/nin-selfies' : 'user-profiles/id-cards';
                                        $uploadResult = $fileUploadService->uploadToCloudinary($uploadedFile, $folderName);
                                        
                                        // Store the Cloudinary URL
                                        $profileData[$key] = $uploadResult['url'];
                                        
                                        // Clean up temp file
                                        Storage::disk('local')->delete($filePath);
                                        break; // Only process the first file
                                    }
                                } catch (\Exception $e) {
                                    Log::error('File upload error: ' . $e->getMessage());
                                    // Skip this file if upload fails
                                    continue;
                                }
                            }
                        } else if (is_string($value) && str_starts_with($value, 'http')) {
                            // Existing URL, keep it
                            $profileData[$key] = $value;
                        }
                    } else {
                        // No new file uploaded, preserve existing value from database
                        $existingValue = $user->profile?->{$key};
                        if ($existingValue) {
                            $profileData[$key] = $existingValue;
                        }
                        // If no existing value, don't set anything (null/empty will be handled by the model)
                    }
                } else {
                    // Handle other profile fields (including empty strings and nulls)
                    $profileData[$key] = $value;
                }
            }
        }

        // Also check for dot notation format (fallback)
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, 'profile.')) {
                $profileKey = str_replace('profile.', '', $key);
                
                // Skip the agent_id field as it's auto-generated
                if ($profileKey === 'agent_id') {
                    continue;
                }
                
                // Only add if not already processed from nested format
                if (!isset($profileData[$profileKey])) {
                    $profileData[$profileKey] = $value;
                }
            }
        }

        // Update or create profile - always do this to ensure profile exists
        if ($user->profile) {
            $user->profile->update($profileData);
            $profile = $user->profile;
        } else {
            $profile = $user->profile()->create($profileData);
        }
        
        // Generate agent ID if this is an agent and doesn't have one
        if ($user->role === User::ROLE_AGENT && !$profile->agent_id) {
            $profile->generateAgentId();
        }
    }
}