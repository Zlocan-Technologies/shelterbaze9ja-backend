<?php

namespace App\Services;

use Cloudinary\Api\Upload\UploadApi;
use CloudinaryLabs\CloudinaryLaravel\Facades\Cloudinary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class FileUploadService
{
    /**
     * Upload file to Cloudinary
     */
    public function uploadToCloudinary(UploadedFile $file, string $folder = 'uploads'): array
    {
        try {

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // Upload to Cloudinary
            $uploadedFileUrl = (new UploadApi())->upload($file->getPathname(), [
                'folder' => $folder,
                'public_id' => pathinfo($filename, PATHINFO_FILENAME),
                'resource_type' => 'auto',
                'transformation' => [
                    'quality' => 'auto',
                    'fetch_format' => 'auto'
                ]
            ]);

            Log::info('File uploaded to Cloudinary', [
                'original_name' => $file->getClientOriginalName(),
                'cloudinary_url' => $uploadedFileUrl,
                'folder' => $folder
            ]);

            return [
                'success' => true,
                'url' => $uploadedFileUrl['url'],
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'folder' => $folder
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload multiple files to Cloudinary
     */
    public function uploadMultipleToCloudinary(array $files, string $folder = 'uploads'): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($files as $file) {
            if ($file instanceof UploadedFile) {
                $result = $this->uploadToCloudinary($file, $folder);
                $results[] = $result;

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failureCount++;
                }
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total' => count($files),
                'success' => $successCount,
                'failed' => $failureCount
            ]
        ];
    }

    /**
     * Upload image with transformations to Cloudinary
     */
    public function uploadImageWithTransformation(UploadedFile $file, string $folder = 'uploads', array $transformations = []): array
    {
        try {
            // Default transformations for images
            $defaultTransformations = [
                'quality' => 'auto',
                'fetch_format' => 'auto',
                'width' => 800,
                'height' => 600,
                'crop' => 'limit'
            ];

            // Merge with custom transformations
            $transformations = array_merge($defaultTransformations, $transformations);

            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // Upload to Cloudinary with transformations
            $uploadedFileUrl = Cloudinary::upload($file->getRealPath(), [
                'folder' => $folder,
                'public_id' => pathinfo($filename, PATHINFO_FILENAME),
                'resource_type' => 'image',
                'transformation' => $transformations
            ])->getSecurePath();

            Log::info('Image uploaded to Cloudinary with transformations', [
                'original_name' => $file->getClientOriginalName(),
                'cloudinary_url' => $uploadedFileUrl,
                'folder' => $folder,
                'transformations' => $transformations
            ]);

            return [
                'success' => true,
                'url' => $uploadedFileUrl,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'transformations' => $transformations
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary image upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'folder' => $folder
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload to local storage as fallback
     */
    public function uploadToLocal(UploadedFile $file, string $folder = 'uploads'): array
    {
        try {
            // Generate unique filename
            $filename = time() . '_' . Str::random(10) . '.' . $file->getClientOriginalExtension();

            // Store file
            $path = $file->storeAs($folder, $filename, 'public');
            $url = Storage::url($path);

            Log::info('File uploaded to local storage', [
                'original_name' => $file->getClientOriginalName(),
                'stored_path' => $path,
                'url' => $url
            ]);

            return [
                'success' => true,
                'url' => $url,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'type' => $file->getMimeType()
            ];
        } catch (\Exception $e) {
            Log::error('Local upload failed', [
                'error' => $e->getMessage(),
                'file' => $file->getClientOriginalName(),
                'folder' => $folder
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Upload with fallback (Cloudinary first, then local)
     */
    public function uploadWithFallback(UploadedFile $file, string $folder = 'uploads'): array
    {
        // Try Cloudinary first
        $cloudinaryResult = $this->uploadToCloudinary($file, $folder);

        if ($cloudinaryResult['success']) {
            return $cloudinaryResult;
        }

        Log::warning('Cloudinary upload failed, falling back to local storage', [
            'cloudinary_error' => $cloudinaryResult['error']
        ]);

        // Fall back to local storage
        return $this->uploadToLocal($file, $folder);
    }

    /**
     * Delete file from Cloudinary
     */
    public function deleteFromCloudinary(string $publicId): array
    {
        try {
            $result = Cloudinary::destroy($publicId);

            Log::info('File deleted from Cloudinary', [
                'public_id' => $publicId,
                'result' => $result
            ]);

            return [
                'success' => true,
                'result' => $result
            ];
        } catch (\Exception $e) {
            Log::error('Cloudinary deletion failed', [
                'error' => $e->getMessage(),
                'public_id' => $publicId
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete file from local storage
     */
    public function deleteFromLocal(string $path): array
    {
        try {
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);

                Log::info('File deleted from local storage', ['path' => $path]);

                return [
                    'success' => true,
                    'message' => 'File deleted successfully'
                ];
            }

            return [
                'success' => false,
                'error' => 'File not found'
            ];
        } catch (\Exception $e) {
            Log::error('Local file deletion failed', [
                'error' => $e->getMessage(),
                'path' => $path
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get file info from URL
     */
    public function getFileInfo(string $url): array
    {
        try {
            // Check if it's a Cloudinary URL
            if (strpos($url, 'cloudinary.com') !== false) {
                return [
                    'type' => 'cloudinary',
                    'url' => $url,
                    'public_id' => $this->extractCloudinaryPublicId($url)
                ];
            }

            // Check if it's a local file URL
            if (strpos($url, '/storage/') !== false) {
                $path = str_replace('/storage/', '', parse_url($url, PHP_URL_PATH));
                return [
                    'type' => 'local',
                    'url' => $url,
                    'path' => $path,
                    'exists' => Storage::disk('public')->exists($path)
                ];
            }

            return [
                'type' => 'external',
                'url' => $url
            ];
        } catch (\Exception $e) {
            return [
                'type' => 'unknown',
                'url' => $url,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Extract public ID from Cloudinary URL
     */
    private function extractCloudinaryPublicId(string $url): string
    {
        // Extract public ID from Cloudinary URL
        $parts = parse_url($url);
        $pathParts = explode('/', $parts['path']);

        // Find the upload index
        $uploadIndex = array_search('upload', $pathParts);
        if ($uploadIndex !== false && isset($pathParts[$uploadIndex + 2])) {
            // Remove file extension
            $publicIdWithExt = implode('/', array_slice($pathParts, $uploadIndex + 2));
            return pathinfo($publicIdWithExt, PATHINFO_FILENAME);
        }

        return '';
    }

    /**
     * Validate file type
     */
    public function validateFile(UploadedFile $file, array $allowedMimes = [], int $maxSizeKB = 5120): array
    {
        $errors = [];

        // Check file size
        if ($file->getSize() > ($maxSizeKB * 1024)) {
            $errors[] = "File size exceeds maximum allowed size of {$maxSizeKB}KB";
        }

        // Check mime type
        if (!empty($allowedMimes) && !in_array($file->getMimeType(), $allowedMimes)) {
            $errors[] = "File type not allowed. Allowed types: " . implode(', ', $allowedMimes);
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Generate thumbnail for image
     */
    public function generateThumbnail(UploadedFile $file, string $folder = 'thumbnails', int $width = 150, int $height = 150): array
    {
        if (!in_array($file->getMimeType(), ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'])) {
            return [
                'success' => false,
                'error' => 'File is not an image'
            ];
        }

        return $this->uploadImageWithTransformation($file, $folder, [
            'width' => $width,
            'height' => $height,
            'crop' => 'fill',
            'gravity' => 'center',
            'quality' => 'auto',
            'fetch_format' => 'auto'
        ]);
    }
}