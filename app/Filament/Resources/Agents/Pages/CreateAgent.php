<?php

namespace App\Filament\Resources\Agents\Pages;

use App\Filament\Resources\Agents\AgentResource;
use App\Models\User;
use App\Services\FileUploadService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CreateAgent extends CreateRecord
{
    protected static string $resource = AgentResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['role'] = User::ROLE_AGENT;
        return $data;
    }

    protected function afterCreate(): void
    {
        $profileData = [];
        $user = $this->record;
        $fileUploadService = new FileUploadService();

        // Extract profile data from the form data
        foreach ($this->data as $key => $value) {
            if (str_starts_with($key, 'profile.')) {
                $profileKey = str_replace('profile.', '', $key);
                
                // Handle file uploads to Cloudinary
                if (in_array($profileKey, ['nin_selfie_url', 'id_card_url']) && $value !== null && $value !== '') {
                    try {
                        // Get the temporary file path
                        $tempFilePath = Storage::disk('local')->path('temp/' . $value);
                        
                        if (file_exists($tempFilePath)) {
                            // Create UploadedFile instance
                            $uploadedFile = new UploadedFile(
                                $tempFilePath,
                                $value,
                                mime_content_type($tempFilePath),
                                null,
                                true
                            );
                            
                            // Upload to Cloudinary
                            $folderName = $profileKey === 'nin_selfie_url' ? 'user-profiles/nin-selfies' : 'user-profiles/id-cards';
                            $uploadResult = $fileUploadService->uploadToCloudinary($uploadedFile, $folderName);
                            
                            // Store the Cloudinary URL
                            $profileData[$profileKey] = $uploadResult['url'];
                            
                            // Clean up temp file
                            Storage::disk('local')->delete('temp/' . $value);
                        }
                    } catch (\Exception $e) {
                        Log::error('File upload error: ' . $e->getMessage());
                        // Skip this file if upload fails
                        continue;
                    }
                } else if ($value !== null && $value !== '') {
                    $profileData[$profileKey] = $value;
                }
            }
        }

        // Create profile if we have profile data
        if (!empty($profileData)) {
            $profile = $user->profile()->create($profileData);
            
            // Generate agent ID if this is an agent
            if ($user->role === User::ROLE_AGENT) {
                $profile->generateAgentId();
            }
        } else {
            // Even if no profile data, create a basic profile for agents to generate agent_id
            if ($user->role === User::ROLE_AGENT) {
                $profile = $user->profile()->create([]);
                $profile->generateAgentId();
            }
        }
    }
}