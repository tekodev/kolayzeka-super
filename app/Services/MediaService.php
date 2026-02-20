<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MediaService
{
    /**
     * Upload a file to S3 and return the temporary URL.
     */
    public function upload(UploadedFile $file, string $directory = 'uploads/generations'): ?string
    {
        if (!$file->isValid()) {
             Log::warning("[MediaService] Invalid file upload attempt");
             return null;
        }

        try {
            $path = $file->store($directory, 's3');
            if ($path) {
                // Return a temporary URL valid for 1 hour
                return Storage::disk('s3')->temporaryUrl($path, now()->addHours(1));
            }
        } catch (\Exception $e) {
            Log::error("[MediaService] S3 Upload Failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Convert an S3 URL (or any accessible URL) to a Base64 string.
     */
    public function convertUrlToBase64(string $url): ?string
    {
        try {
            // Optimization: If the URL is from our own S3 bucket, read it directly
            $s3Bucket = config('filesystems.disks.s3.bucket');
            if ($s3Bucket && str_contains($url, $s3Bucket . '.s3')) {
                // ... (existing S3 logic)
                $path = parse_url($url, PHP_URL_PATH);
                $path = ltrim($path, '/');
                if (Storage::disk('s3')->exists($path)) {
                    return base64_encode(Storage::disk('s3')->get($path));
                }
            }

            // Optimization 2: If the URL is local (localhost), read from public storage directly
            // This is critical for artisan serve which is single-threaded.
            if (str_contains($url, 'localhost') || str_contains($url, '127.0.0.1')) {
                $path = parse_url($url, PHP_URL_PATH); // e.g. /storage/app_static_assets/luna_identity.jpg
                if (str_starts_with($path, '/storage/')) {
                    $relativePath = str_replace('/storage/', '', $path);
                    if (Storage::disk('public')->exists($relativePath)) {
                        Log::info("[MediaService] Resolved local URL to filesystem: {$path}");
                        return base64_encode(Storage::disk('public')->get($relativePath));
                    }
                }
            }

            $response = Http::timeout(120)->get($url);
            
            if ($response->successful()) {
                return base64_encode($response->body());
            }
            
            Log::warning("[MediaService] Failed to download URL for Base64 conversion: {$url} - Status: " . $response->status());
        } catch (\Exception $e) {
            Log::error("[MediaService] Base64 Conversion Failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Upload Base64 encoded data to S3 and return the URL.
     */
    public function uploadBase64ToS3(string $base64Data, string $directory = 'uploads/generations', string $extension = 'jpg'): ?string
    {
        try {
            // Decode Base64
            $fileData = base64_decode($base64Data);
            
            if (!$fileData) {
                Log::warning("[MediaService] Failed to decode Base64 data");
                return null;
            }
            
            // Generate unique filename
            $filename = $directory . '/' . uniqid() . '.' . $extension;
            
            // Upload to S3
            $uploaded = Storage::disk('s3')->put($filename, $fileData);
            
            if ($uploaded) {
                // Return temporary URL valid for 1 hour
                return Storage::disk('s3')->temporaryUrl($filename, now()->addHours(1));
            }
            
            Log::warning("[MediaService] Failed to upload Base64 data to S3");
        } catch (\Exception $e) {
            Log::error("[MediaService] Base64 S3 Upload Failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate a thumbnail from Base64 image data and upload to S3.
     * 
     * @param string $base64Data Base64 encoded image
     * @param int $maxWidth Maximum width (default: 300px)
     * @param int $quality JPEG quality 0-100 (default: 60)
     * @return string|null S3 URL of thumbnail
     */
    public function generateThumbnail(string $base64Data, int $maxWidth = 300, int $quality = 60): ?string
    {
        try {
            // Decode Base64
            $imageData = base64_decode($base64Data);
            
            if (!$imageData) {
                Log::warning("[MediaService] Failed to decode Base64 for thumbnail");
                return null;
            }
            
            // Create image from string
            $image = imagecreatefromstring($imageData);
            
            if (!$image) {
                Log::warning("[MediaService] Failed to create image from Base64");
                return null;
            }
            
            // Get original dimensions
            $originalWidth = imagesx($image);
            $originalHeight = imagesy($image);
            
            // Calculate new dimensions (maintain aspect ratio)
            if ($originalWidth > $maxWidth) {
                $newWidth = $maxWidth;
                $newHeight = (int) ($originalHeight * ($maxWidth / $originalWidth));
            } else {
                $newWidth = $originalWidth;
                $newHeight = $originalHeight;
            }
            
            // Create thumbnail
            $thumbnail = imagecreatetruecolor($newWidth, $newHeight);
            imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);
            
            // Save to buffer
            ob_start();
            imagejpeg($thumbnail, null, $quality);
            $thumbnailData = ob_get_clean();
            
            // Clean up
            imagedestroy($image);
            imagedestroy($thumbnail);
            
            // Upload to S3
            $filename = 'uploads/thumbnails/' . uniqid() . '.jpg';
            $uploaded = Storage::disk('s3')->put($filename, $thumbnailData);
            
            if ($uploaded) {
                return Storage::disk('s3')->temporaryUrl($filename, now()->addHours(1));
            }
            
            Log::warning("[MediaService] Failed to upload thumbnail to S3");
        } catch (\Exception $e) {
            Log::error("[MediaService] Thumbnail Generation Failed: " . $e->getMessage());
        }

        return null;
    }
}

