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

    // URI from previous upload
    $fileUri = 'https://generativelanguage.googleapis.com/v1beta/files/yjstylpjlkbw';
    
    // API endpoint doesn't use the full URI usually, just the resource ID.
    // The File URI *is* the resource ID in format files/.....
    // But REST endpoint is GET on that same URI + ?key=...
    
    echo "Verifying file status at Google...\n";
    echo "Using File URI: $fileUri\n";
    
    $response = Http::get("{$fileUri}?key={$apiKey}");
    
    if ($response->successful()) {
        $data = $response->json();
        
        echo "\n--- FILE STATUS ---\n";
        echo "Name (ID): " . ($data['name'] ?? 'N/A') . "\n";
        echo "Display Name: " . ($data['displayName'] ?? 'N/A') . "\n";
        echo "MIME Type: " . ($data['mimeType'] ?? 'N/A') . "\n";
        echo "Size Bytes: " . ($data['sizeBytes'] ?? 'N/A') . "\n";
        echo "Create Time: " . ($data['createTime'] ?? 'N/A') . "\n";
        echo "Update Time: " . ($data['updateTime'] ?? 'N/A') . "\n";
        echo "Expiration Time: " . ($data['expirationTime'] ?? 'N/A') . "\n";
        echo "State: " . ($data['state'] ?? 'N/A') . "\n"; // Should be ACTIVE
        
        if (($data['state'] ?? '') === 'ACTIVE') {
            echo "\n✅ SUCCESS: File is ACTIVE and ready for use by the model.\n";
        } else {
             echo "\n⚠️ WARNING: File state is " . ($data['state'] ?? 'UNKNOWN') . " (Processing?)\n";
        }
        
        echo "\nNote: This is a private API resource for the model, not a public viewable link.\n";
        
    } else {
        echo "Verification Failed:\n";
        echo "Status: " . $response->status() . "\n";
        echo "Body: " . $response->body() . "\n";
    }

} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
