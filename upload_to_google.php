<?php
// Load Laravel environment
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

try {
    $apiKey = config('services.google.key');
    if (!$apiKey) {
        die("Error: Google API Key missing in .env (services.google.key)\n");
    }

    $filePath = storage_path('app/public/luna_identity.jpg');
    if (!file_exists($filePath)) {
        die("Error: Identity image not found at $filePath\n");
    }

    $fileSize = filesize($filePath);
    $mimeType = 'image/jpeg';
    
    echo "Uploading file to Google Gemini File API...\n";
    echo "File: $filePath ($fileSize bytes)\n";

    // Google File API upload
    // Step 1: Initialize Upload (Optional for small files, but safer for 9MB)
    // Actually, simple POST with raw body works for < 2GB if just raw binary
    // Endpoint: https://generativelanguage.googleapis.com/upload/v1beta/files?key=$apiKey
    
    // We need to send Metadata first + upload url, OR direct upload.
    // Let's use the simple direct upload method if supported or multipart?
    // According to docs, the endpoint is /upload/v1beta/files
    // We need to pass metadata "file": {"display_name": "..."} in a separate part if multipart.
    
    // Simpler: Just try standard upload.
    
    $response = Http::withHeaders([
        'X-Goog-Upload-Protocol' => 'raw',
        'X-Goog-Upload-Header-Content-Length' => $fileSize,
        'X-Goog-Upload-Header-Content-Type' => $mimeType,
        'Content-Type' => $mimeType, // The actual content type
    ])->withBody(
        file_get_contents($filePath), $mimeType
    )->post("https://generativelanguage.googleapis.com/upload/v1beta/files?key={$apiKey}");

    if ($response->successful()) {
        $data = $response->json();
        // The direct upload response contains 'file' object
        if (isset($data['file']['uri'])) {
            echo "\nSUCCESS! File Uploaded.\n";
            echo "File URI: " . $data['file']['uri'] . "\n";
            echo "Display Name: " . ($data['file']['displayName'] ?? 'N/A') . "\n";
            echo "State: " . ($data['file']['state'] ?? 'N/A') . "\n";
        } else {
             echo "Upload successful but URI missing in response:\n";
             print_r($data);
        }
    } else {
        echo "Upload Failed:\n";
        echo "Status: " . $response->status() . "\n";
        echo "Body: " . $response->body() . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
