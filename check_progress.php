<?php
// Turn off warnings and notices that could interfere with JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/security.php';

// Ensure user is logged in
requireAuthentication();

// Check if ComfyUI is available
function isComfyUIAvailable() {
    $ch = curl_init(COMFYUI_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if (!empty($error)) {
        error_log("ComfyUI connection error: " . $error);
        return false;
    }
    
    return $httpCode >= 200 && $httpCode < 300;
}

// Main function
header('Content-Type: application/json');

// Validate input
if (!isset($_GET['prompt_id']) || empty($_GET['prompt_id'])) {
    echo json_encode(['error' => 'Missing prompt ID']);
    exit;
}

$prompt_id = $_GET['prompt_id'];

// Check if ComfyUI is available
if (!isComfyUIAvailable()) {
    echo json_encode(['error' => 'ComfyUI is not available. Please try again later.']);
    exit;
}

// Get generation status from ComfyUI
$ch = curl_init(COMFYUI_URL . '/api/history/' . $prompt_id);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$historyResponse = curl_exec($ch);

if(curl_errno($ch)) {
    echo json_encode(['error' => 'Error checking history: ' . curl_error($ch)]);
    curl_close($ch);
    exit;
}

curl_close($ch);

if (!$historyResponse) {
    echo json_encode(['error' => 'No response from ComfyUI']);
    exit;
}

$historyData = json_decode($historyResponse, true);

// Check if there's an error message in the history
if (isset($historyData[$prompt_id]['error'])) {
    echo json_encode(['error' => 'ComfyUI error: ' . $historyData[$prompt_id]['error']]);
    exit;
}

// Check if we have image outputs
if (isset($historyData[$prompt_id]['outputs'])) {
    foreach ($historyData[$prompt_id]['outputs'] as $node) {
        if (isset($node['images'])) {
            // We found images, return them with proper URL
            $images = [];
            foreach ($node['images'] as $image) {
                // Build URL to the image on ComfyUI server
                $images[] = [
                    'filename' => $image['filename'],
                    'url' => COMFYUI_URL . '/view?filename=' . urlencode($image['filename']),
                    'type' => $image['type'] ?? 'output'
                ];
            }
            echo json_encode([
                'status' => 'success',
                'images' => $images
            ]);
            exit;
        }
    }
}

// If no images yet, check for progress
$progress = 0;
$status = 'processing';

if (isset($historyData[$prompt_id]['status'])) {
    $status = $historyData[$prompt_id]['status']['status'] ?? 'processing';
    $progress = $historyData[$prompt_id]['status']['progress'] ?? 0;
}

echo json_encode([
    'status' => $status,
    'progress' => $progress,
    'prompt_id' => $prompt_id
]);
?> 