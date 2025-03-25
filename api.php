<?php
// Turn off warnings and notices that could interfere with JSON output
error_reporting(E_ERROR | E_PARSE);
ini_set('display_errors', 0);

// Increase PHP execution time limit to prevent timeouts
set_time_limit(600); // Increase to 10 minutes
ini_set('memory_limit', '512M'); // Increase memory limit if needed

require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if ComfyUI is available
function isComfyUIAvailable() {
    $ch = curl_init(COMFYUI_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3); // Reduced timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 3); // Reduced timeout
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

/**
 * Get workflow file path from database
 */
function getWorkflowFile($workflowId) {
    global $conn;
    $stmt = $conn->prepare("SELECT api_file FROM workflows WHERE id = ?");
    $stmt->bind_param("i", $workflowId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    if (!$row) {
        return false;
    }
    
    // Return the full path including the upload directory
    $filePath = UPLOAD_DIR . $row['api_file'];
    
    // Check if the file exists, if not try to find an alternative
    if (!file_exists($filePath)) {
        error_log("Workflow file not found: $filePath - Attempting to find a replacement");
        
        // Extract the base workflow name (without timestamp prefix)
        $fileNameParts = explode('_', $row['api_file'], 2);
        if (count($fileNameParts) > 1) {
            $baseName = $fileNameParts[1];
            
            // Look for any file with the same base name
            $files = scandir(UPLOAD_DIR);
            foreach ($files as $file) {
                if (strpos($file, $baseName) !== false) {
                    $alternativeFile = UPLOAD_DIR . $file;
                    error_log("Found alternative workflow file: $alternativeFile");
                    
                    // Update the database to use this file in the future
                    $updateStmt = $conn->prepare("UPDATE workflows SET api_file = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $file, $workflowId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    return $alternativeFile;
                }
            }
        }
        
        // If we can't find a direct replacement, get any workflow file of the same type
        // Check if it's a photo, video, inpaint, etc. workflow
        $workflowType = '';
        if (strpos($row['api_file'], 'photo') !== false) $workflowType = 'photo';
        elseif (strpos($row['api_file'], 'video') !== false) $workflowType = 'video';
        elseif (strpos($row['api_file'], 'inpaint') !== false) $workflowType = 'inpaint';
        elseif (strpos($row['api_file'], 'lora') !== false) $workflowType = 'lora';
        
        if (!empty($workflowType)) {
            $files = scandir(UPLOAD_DIR);
            foreach ($files as $file) {
                if (strpos($file, $workflowType) !== false) {
                    $alternativeFile = UPLOAD_DIR . $file;
                    error_log("Found alternative workflow file of type '$workflowType': $alternativeFile");
                    
                    // Update the database to use this file in the future
                    $updateStmt = $conn->prepare("UPDATE workflows SET api_file = ? WHERE id = ?");
                    $updateStmt->bind_param("si", $file, $workflowId);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    return $alternativeFile;
                }
            }
        }
        
        // Last resort - get any JSON file
        $files = scandir(UPLOAD_DIR);
        foreach ($files as $file) {
            if (pathinfo($file, PATHINFO_EXTENSION) === 'json') {
                $alternativeFile = UPLOAD_DIR . $file;
                error_log("Last resort - using any JSON file: $alternativeFile");
                
                // Update the database to use this file in the future
                $updateStmt = $conn->prepare("UPDATE workflows SET api_file = ? WHERE id = ?");
                $updateStmt->bind_param("si", $file, $workflowId);
                $updateStmt->execute();
                $updateStmt->close();
                
                return $alternativeFile;
            }
        }
        
        error_log("Failed to find any alternative workflow file");
        return false;
    }
    
    return $filePath;
}

/**
 * Run ComfyUI workflow with provided inputs
 */
function runWorkflow($workflowFile, $inputs) {
    // Read workflow JSON
    $json = file_get_contents($workflowFile);
    if (!$json) {
        return ['error' => 'Could not read workflow file: ' . $workflowFile];
    }
    
    $workflow = json_decode($json, true);
    if (!$workflow) {
        return ['error' => 'Invalid JSON in workflow file'];
    }

    error_log("Original workflow JSON: " . $json);
    error_log("Processing workflow: " . json_encode($workflow));
    
    // First find the KSampler node to identify positive and negative connections
    $positiveNodeId = null;
    $negativeNodeId = null;
    
    if (isset($workflow['nodes'])) {
        // Standard workflow format with nodes property
        foreach ($workflow['nodes'] as $node) {
            if (isset($node['class_type']) && 
                (strpos($node['class_type'], 'KSampler') !== false || strpos($node['class_type'], 'Sampler') !== false)) {
                if (isset($node['inputs']['positive']) && is_array($node['inputs']['positive'])) {
                    $positiveNodeId = $node['inputs']['positive'][0];
                    error_log("Found positive node ID: " . $positiveNodeId);
                }
                if (isset($node['inputs']['negative']) && is_array($node['inputs']['negative'])) {
                    $negativeNodeId = $node['inputs']['negative'][0];
                    error_log("Found negative node ID: " . $negativeNodeId);
                }
            }
        }
    } else {
        // Flat structure
        foreach ($workflow as $nodeId => $node) {
            if (isset($node['class_type']) && 
                (strpos($node['class_type'], 'KSampler') !== false || strpos($node['class_type'], 'Sampler') !== false)) {
                if (isset($node['inputs']['positive']) && is_array($node['inputs']['positive'])) {
                    $positiveNodeId = $node['inputs']['positive'][0];
                    error_log("Found positive node ID: " . $positiveNodeId);
                }
                if (isset($node['inputs']['negative']) && is_array($node['inputs']['negative'])) {
                    $negativeNodeId = $node['inputs']['negative'][0];
                    error_log("Found negative node ID: " . $negativeNodeId);
                }
            }
        }
    }
    
    // Replace inputs in workflow JSON
    foreach ($inputs as $key => $value) {
        // Skip the cache_buster parameter - it's only used to prevent caching
        if ($key === 'cache_buster' || $key === '_timestamp') {
            continue;
        }
        
        // For number inputs, convert to appropriate type
        if (is_numeric($value)) {
            if (strpos($value, '.') !== false) {
                $value = (float)$value;
            } else {
                $value = (int)$value;
            }
        }
        
        error_log("Setting input $key to " . json_encode($value));
        
        // Process prompt and negative_prompt specially
        if ($key === 'prompt' || $key === 'negative_prompt') {
            // Debug: Log the prompt value being processed
            error_log("Processing {$key} with value: " . $value);
            
            // For smallphoto.json, directly update nodes 6 and 7
            if (basename($api_file) === 'smallphoto.json') {
                if ($key === 'prompt' && isset($workflow['6']) && isset($workflow['6']['inputs'])) {
                    $workflow['6']['inputs']['text'] = $value;
                    error_log("SMALLPHOTO: Set positive prompt in node 6: " . $value);
                }
                
                if ($key === 'negative_prompt' && isset($workflow['7']) && isset($workflow['7']['inputs'])) {
                    $workflow['7']['inputs']['text'] = $value;
                    error_log("SMALLPHOTO: Set negative prompt in node 7: " . $value);
                }
                
                // Skip the rest of the processing for this key
                continue;
            }
            
            if (isset($workflow['nodes'])) {
                // Standard workflow format
                foreach ($workflow['nodes'] as $index => &$node) {
                    if (isset($node['class_type']) && strpos($node['class_type'], 'CLIPTextEncode') !== false) {
                        // For prompts, check if this is the right node (positive or negative)
                        $isNegativeNode = false;
                        
                        // Check if it's connected to the negative input of the sampler
                        if (isset($workflow['nodes']) && is_numeric($negativeNodeId)) {
                            // Safely get node ID using index
                            $nodeKeys = array_keys($workflow['nodes']);
                            if (isset($nodeKeys[$index]) && $nodeKeys[$index] == $negativeNodeId) {
                                $isNegativeNode = true;
                            }
                        }
                        
                        // Also check meta title for "negative" keyword if available
                        if (isset($node['_meta']['title']) && 
                            (stripos($node['_meta']['title'], 'negative') !== false)) {
                            $isNegativeNode = true;
                        }
                        
                        // Set the value in the appropriate node
                        if (($key === 'negative_prompt' && $isNegativeNode) || 
                            ($key === 'prompt' && !$isNegativeNode)) {
                            $node['inputs']['text'] = $value;
                            error_log("Set " . ($isNegativeNode ? "negative" : "positive") . " prompt in CLIP node");
                        }
                    }
                }
            } else {
                // Flat structure - for smallphoto.json, node 6 is positive prompt, node 7 is negative prompt
                if ($key === 'prompt' && isset($workflow['6']) && isset($workflow['6']['inputs']['text'])) {
                    $workflow['6']['inputs']['text'] = $value;
                    error_log("Set positive prompt in node 6: " . $value);
                }
                
                if ($key === 'negative_prompt' && isset($workflow['7']) && isset($workflow['7']['inputs']['text'])) {
                    $workflow['7']['inputs']['text'] = $value;
                    error_log("Set negative prompt in node 7: " . $value);
                }
                
                // Also try to find by class_type for other workflows
                foreach ($workflow as $nodeId => &$node) {
                    if (isset($node['class_type']) && strpos($node['class_type'], 'CLIPTextEncode') !== false) {
                        // Check if this is the negative prompt node
                        $isNegativeNode = false;
                        
                        // Safely check if this node ID matches the negative node ID
                        if ($nodeId == $negativeNodeId) {
                            $isNegativeNode = true;
                        }
                        
                        // Also check meta title for "negative" keyword if available
                        if (isset($node['_meta']['title']) && 
                            (stripos($node['_meta']['title'], 'negative') !== false)) {
                            $isNegativeNode = true;
                        }
                        
                        // Set the value in the appropriate node
                        if (($key === 'negative_prompt' && $isNegativeNode) || 
                            ($key === 'prompt' && !$isNegativeNode)) {
                            $node['inputs']['text'] = $value;
                            error_log("Set " . ($isNegativeNode ? "negative" : "positive") . " prompt in node " . $nodeId);
                        }
                    }
                }
            }
        } else {
            // Handle other inputs (non-prompt)
            if (isset($workflow['nodes'])) {
                // Standard workflow format with nodes property
                foreach ($workflow['nodes'] as &$node) {
                    if (isset($node['inputs']) && isset($node['inputs'][$key]) && !is_array($node['inputs'][$key])) {
                        $node['inputs'][$key] = $value;
                    }
                }
            } else {
                // Flat workflow format without nodes property
                foreach ($workflow as &$node) {
                    if (isset($node['inputs']) && isset($node['inputs'][$key]) && !is_array($node['inputs'][$key])) {
                        $node['inputs'][$key] = $value;
                    }
                }
            }
        }
    }

    // Add a random seed to force new generation every time
    if (isset($workflow['nodes'])) {
        // Standard workflow format
        foreach ($workflow['nodes'] as &$node) {
            if (isset($node['class_type']) && (
                strpos($node['class_type'], 'KSampler') !== false || 
                strpos($node['class_type'], 'Sampler') !== false
            )) {
                // Force random seed by setting to -1 or using a new random seed each time
                if (isset($node['inputs']['seed'])) {
                    $node['inputs']['seed'] = rand(1, 999999999);
                    error_log("Set random seed to: " . $node['inputs']['seed']);
                }
            }
        }
        
        // Remove any metadata fields that might be causing issues
        if (isset($workflow['__metadata'])) {
            unset($workflow['__metadata']);
        }
        if (isset($workflow['_metadata'])) {
            unset($workflow['_metadata']);
        }
    } else {
        // Flat structure
        foreach ($workflow as &$node) {
            if (isset($node['class_type']) && (
                strpos($node['class_type'], 'KSampler') !== false || 
                strpos($node['class_type'], 'Sampler') !== false
            )) {
                // Force random seed by setting to -1 or using a new random seed each time
                if (isset($node['inputs']['seed'])) {
                    $node['inputs']['seed'] = rand(1, 999999999);
                    error_log("Set random seed to: " . $node['inputs']['seed']);
                }
            }
        }
        
        // Remove any metadata fields that might be causing issues
        if (isset($workflow['__metadata'])) {
            unset($workflow['__metadata']);
        }
        if (isset($workflow['_metadata'])) {
            unset($workflow['_metadata']);
        }
    }
    
    // Remove the problematic _cache_buster if it exists
    if (isset($workflow['_cache_buster'])) {
        unset($workflow['_cache_buster']);
    }
    
    $data = ['prompt' => $workflow];
    $jsonData = json_encode($data);
    error_log("Sending to ComfyUI: " . $jsonData);
    
    // Debug: Log the workflow JSON being sent to ComfyUI
    error_log("Sending workflow to ComfyUI: " . json_encode($workflow, JSON_PRETTY_PRINT));
    
    // Send the modified workflow to ComfyUI
    $ch = curl_init(COMFYUI_URL . '/api/prompt');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10); // Connection timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 180);       // Operation timeout (3 minutes)
    $response = curl_exec($ch);
    
    if(curl_errno($ch)) {
        $error = ['error' => 'Curl error: ' . curl_error($ch)];
        curl_close($ch);
        return $error;
    }
    
    curl_close($ch);
    $responseData = json_decode($response, true);
    error_log("ComfyUI response: " . $response);
    
    // If we have a prompt_id, we need to wait for processing to complete
    if (isset($responseData['prompt_id'])) {
        $promptId = $responseData['prompt_id'];
        $attempts = 0;
        $maxAttempts = 240; // Increase to 240 seconds (4 minutes) max wait time
        
        while ($attempts < $maxAttempts) {
            // Wait 1 second between checks
            sleep(1);
            $attempts++;
            
            error_log("Waiting for image generation, attempt $attempts of $maxAttempts");
            
            // Check if the image is ready
            $ch = curl_init(COMFYUI_URL . '/api/history/' . $promptId);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $historyResponse = curl_exec($ch);
            
            if(curl_errno($ch)) {
                error_log("Error checking history: " . curl_error($ch));
                curl_close($ch);
                continue; // Try again on next attempt
            }
            
            curl_close($ch);
            
            if ($historyResponse) {
                $historyData = json_decode($historyResponse, true);
                
                // Check if there's an error message in the history
                if (isset($historyData[$promptId]['error'])) {
                    error_log("ComfyUI error: " . $historyData[$promptId]['error']);
                    return ['error' => 'ComfyUI error: ' . $historyData[$promptId]['error']];
                }
                
                // Check if we have image outputs
                if (isset($historyData[$promptId]['outputs'])) {
                    foreach ($historyData[$promptId]['outputs'] as $node) {
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
                            return [
                                'success' => true,
                                'images' => $images
                            ];
                        }
                    }
                }
                
                // Check if there's a progress status
                if (isset($historyData[$promptId]['status'])) {
                    error_log("ComfyUI status: " . $historyData[$promptId]['status']['status']);
                    
                    // If we have percent complete information, log it
                    if (isset($historyData[$promptId]['status']['progress'])) {
                        $progress = $historyData[$promptId]['status']['progress'] * 100;
                        error_log("Processing progress: " . round($progress) . "%");
                    }
                }
            }
        }
        
        // If we got here, we timed out. Let's check one more time for errors in the history.
        $ch = curl_init(COMFYUI_URL . '/api/history/' . $promptId);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $historyResponse = curl_exec($ch);
        curl_close($ch);
        
        if ($historyResponse) {
            $historyData = json_decode($historyResponse, true);
            if (isset($historyData[$promptId]['error'])) {
                return ['error' => 'ComfyUI error: ' . $historyData[$promptId]['error']];
            }
        }
        
        return [
            'error' => 'Timed out waiting for image generation after ' . $maxAttempts . ' seconds. The workflow may be too complex or ComfyUI might be overloaded.',
            'prompt_id' => $promptId
        ];
    }
    
    // If we don't have a prompt_id, return whatever response we got
    return $responseData;
}

// Main API function
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    session_start();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'User not logged in']);
        exit;
    }
    
    $user_id = $_SESSION['user_id'];
    
    // Validate input
    if (!isset($_POST['workflow_id']) || !isset($_POST['inputs'])) {
        echo json_encode(['error' => 'Missing required parameters']);
        exit;
    }
    
    $workflow_id = (int)$_POST['workflow_id'];
    $inputs = [];
    if (isset($_POST['inputs'])) {
        $inputs = json_decode($_POST['inputs'], true);
        error_log("Received inputs JSON: " . $_POST['inputs']);
    } else {
        // If no inputs JSON provided, try to get individual inputs from POST
        foreach ($_POST as $key => $value) {
            if ($key !== 'workflow_id' && $key !== 'workflow_point_cost' && $key !== 'cache_buster') {
                $inputs[$key] = $value;
            }
        }
        error_log("Extracted inputs from POST: " . json_encode($inputs));
    }
    
    error_log("All inputs to be processed: " . json_encode($inputs));
    
    if ($workflow_id <= 0) {
        echo json_encode(['error' => 'Invalid workflow ID']);
        exit;
    }
    
    // Check if ComfyUI is available
    if (!isComfyUIAvailable()) {
        echo json_encode(['error' => 'ComfyUI is not available. Please try again later or contact an administrator.']);
        exit;
    }
    
    // Get workflow
    $stmt = $conn->prepare("SELECT id, name, api_file, inputs, point_cost FROM workflows WHERE id = ?");
    $stmt->bind_param("i", $workflow_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $workflow = $result->fetch_assoc();
    $stmt->close();
    
    if (!$workflow) {
        echo json_encode(['error' => 'Workflow not found']);
        exit;
    }
    
    // Check user's points
    $user_stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user = $user_result->fetch_assoc();
    $user_stmt->close();
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Check if user has enough points
    $point_cost = $workflow['point_cost'];
    if ($user['points'] < $point_cost) {
        echo json_encode(['error' => 'You don\'t have enough points to use this workflow. Required: ' . $point_cost . ', Available: ' . $user['points']]);
        exit;
    }
    
    // Get workflow file path
    $workflowFile = getWorkflowFile($workflow_id);
    if (!$workflowFile || !file_exists($workflowFile)) {
        echo json_encode(['error' => 'Workflow file not found: ' . $workflowFile]);
        exit;
    }
    
    // Run workflow
    $result = runWorkflow($workflowFile, $inputs);
    
    // If generation was successful, deduct points
    if (isset($result['images']) || isset($result['url']) || isset($result['image']) || 
        (isset($result['output']) && isset($result['output']['images']))) {
        
        // Deduct points from user
        $update_stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
        $update_stmt->bind_param("ii", $point_cost, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        
        // Store the point cost in the result for frontend
        $result['points_used'] = $point_cost;
        $result['points_remaining'] = $user['points'] - $point_cost;
    }
    
    echo json_encode($result);
}
?> 