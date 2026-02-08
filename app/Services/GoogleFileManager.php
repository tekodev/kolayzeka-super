<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class GoogleFileManager
{
    protected string $apiKey;
    protected string $baseUrl = 'https://generativelanguage.googleapis.com';

    public function __construct()
    {
        $this->apiKey = config('services.google.key');
    }

    /**
     * Get a valid Google File URI for a local file.
     * Uploads if not cached or expired.
     */
    public function getUri(string $filePath, string $mimeType): ?string
    {
        if (!file_exists($filePath)) {
            Log::error("GoogleFileManager: File not found at $filePath");
            return null;
        }

        // Generate a cache key based on file content hash to ensure uniqueness
        $fileHash = md5_file($filePath);
        $cacheKey = "google_file_uri_{$fileHash}";

        // Return cached URI if available
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // If not in cache, upload and cache
        return $this->uploadAndCache($filePath, $mimeType, $cacheKey);
    }

    protected function uploadAndCache(string $filePath, string $mimeType, string $cacheKey): ?string
    {
        try {
            $fileSize = filesize($filePath);
            $fileContent = file_get_contents($filePath);

            Log::info("GoogleFileManager: Uploading $filePath ($fileSize bytes)...");

            $response = Http::withHeaders([
                'X-Goog-Upload-Protocol' => 'raw',
                'X-Goog-Upload-Header-Content-Length' => $fileSize,
                'X-Goog-Upload-Header-Content-Type' => $mimeType,
                'Content-Type' => $mimeType,
            ])->withBody(
                $fileContent, $mimeType
            )->post("{$this->baseUrl}/upload/v1beta/files?key={$this->apiKey}");

            if ($response->successful()) {
                $data = $response->json();
                $uri = $data['file']['uri'];
                $name = $data['file']['name']; // e.g. files/xxxx
                
                // Expiration is usually 48 hours. Let's cache for 46 hours to be safe.
                // We could parse 'expirationTime' from response but 46h is a safe default for now.
                $ttl = now()->addHours(46);

                Cache::put($cacheKey, $uri, $ttl);
                
                Log::info("GoogleFileManager: Upload Successful. URI: $uri. Cached until $ttl");

                return $uri;
            } else {
                Log::error("GoogleFileManager: Upload Failed. " . $response->body());
                return null;
            }

        } catch (\Exception $e) {
            Log::error("GoogleFileManager: Exception " . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload a file from local path to Google File API
     * Alias for getUri() for better readability
     */
    public function uploadFile(string $filePath, string $displayName = 'image.png'): ?string
    {
        return $this->getUri($filePath, 'image/png');
    }

    /**
     * Download image from URL and upload to Google File API
     */
    public function uploadFromUrl(string $url, string $displayName = 'image.png'): ?string
    {
        try {
            Log::info("GoogleFileManager: Downloading from URL: $url");
            
            // Download image
            $response = Http::get($url);
            
            if (!$response->successful()) {
                Log::error("GoogleFileManager: Failed to download from URL: $url");
                return null;
            }

            // Save to temp file
            $tempPath = sys_get_temp_dir() . '/' . uniqid('google_upload_') . '.png';
            file_put_contents($tempPath, $response->body());

            // Upload to Google
            $uri = $this->getUri($tempPath, 'image/png');

            // Clean up temp file
            @unlink($tempPath);

            return $uri;
        } catch (\Exception $e) {
            Log::error("GoogleFileManager: Exception downloading from URL: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get or upload a static file (like luna_face.png)
     * Uses a persistent cache key based on filename
     */
    public function getOrUploadStaticFile(string $filePath, string $staticName): ?string
    {
        // For static files, use filename as cache key (not content hash)
        // This way the same file always gets the same cached URI
        $cacheKey = "google_file_static_{$staticName}";

        // Return cached URI if available
        if (Cache::has($cacheKey)) {
            Log::info("GoogleFileManager: Using cached URI for static file: $staticName");
            return Cache::get($cacheKey);
        }

        // Upload and cache
        Log::info("GoogleFileManager: Uploading static file: $staticName");
        return $this->uploadAndCache($filePath, 'image/png', $cacheKey);
    }
}
