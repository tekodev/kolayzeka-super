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
        die("Error: Google API Key missing.\n");
    }

    $filePath = storage_path('app/public/luna_face.png');
    if (!file_exists($filePath)) {
        die("Error: File not found at $filePath\n");
    }

    $mimeType = 'image/png';
    $fileSize = filesize($filePath);

    echo "Uploading luna_face.png ($fileSize bytes)...\n";

    // 1. Initiate Resumable Upload (or simple upload for small files, but SDK uses resumable usually. 
    // We will use the consistent 'upload/v1beta/files' endpoint for straightforward upload if small enough, 
    // or standard POST for simplicity as used before).
    
    // Using the same method as previous successful upload
    $response = Http::withHeaders([
        'X-Goog-Upload-Protocol' => 'raw',
        'X-Goog-Upload-Header-Content-Length' => $fileSize,
        'X-Goog-Upload-Header-Content-Type' => $mimeType,
        'Content-Type' => $mimeType,
    ])->withBody(
        file_get_contents($filePath), $mimeType
    )->post("https://generativelanguage.googleapis.com/upload/v1beta/files?key={$apiKey}");

    if ($response->successful()) {
        $data = $response->json();
        echo "\nSUCCESS!\n";
        echo "File URI: " . $data['file']['uri'] . "\n";
        echo "Name: " . $data['file']['name'] . "\n";
        echo "State: " . $data['file']['state'] . "\n";
    } else {
        echo "\nUpload Failed:\n";
        echo "Status: " . $response->status() . "\n";
        echo "Body: " . $response->body() . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
