<?php
require_once 'includes/db.php';
require_once 'includes/security.php';

// Ensure user is logged in using security module
requireAuthentication();

$user_id = $_SESSION['user_id'];

// Verify that the user exists in the database
$check_user_query = "SELECT id FROM users WHERE id = ?";
$check_stmt = $conn->prepare($check_user_query);
$check_stmt->bind_param("i", $user_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_stmt->close();

if ($check_result->num_rows === 0) {
    error_log("User ID $user_id not found in the users table");
    echo json_encode(['error' => 'User not found in database. Please log out and log in again.']);
    exit;
}

$workflow_id = isset($_POST['workflow_id']) ? (int)$_POST['workflow_id'] : 0;
$image_url = isset($_POST['image_url']) ? $_POST['image_url'] : '';
$image_filename = isset($_POST['image_filename']) ? $_POST['image_filename'] : 'generated-image.png';
$points_used = isset($_POST['points_used']) ? (int)$_POST['points_used'] : 0;
$save_to_gallery = isset($_POST['save_to_gallery']) ? (bool)$_POST['save_to_gallery'] : false;

// Add debugging
error_log("Saving generation for user: $user_id, workflow: $workflow_id, URL length: " . strlen($image_url) . 
          ", Points used: $points_used, Save to gallery: " . ($save_to_gallery ? 'true' : 'false'));

if ($workflow_id <= 0) {
    echo json_encode(['error' => 'Invalid workflow ID']);
    exit;
}

// Verify that the workflow exists in the database
$check_workflow_query = "SELECT id FROM workflows WHERE id = ?";
$check_stmt = $conn->prepare($check_workflow_query);
$check_stmt->bind_param("i", $workflow_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_stmt->close();

if ($check_result->num_rows === 0) {
    error_log("Workflow ID $workflow_id not found in the workflows table");
    echo json_encode(['error' => 'Workflow not found in database. Please try a different workflow.']);
    exit;
}

if (empty($image_url)) {
    echo json_encode(['error' => 'Image URL is required']);
    exit;
}

// Get workflow info
$workflow_query = "SELECT name, category, point_cost FROM workflows WHERE id = ?";
$stmt = $conn->prepare($workflow_query);
$stmt->bind_param("i", $workflow_id);
$stmt->execute();
$workflow_result = $stmt->get_result();
$workflow = $workflow_result->fetch_assoc();
$stmt->close();

if (!$workflow) {
    echo json_encode(['error' => 'Workflow not found']);
    exit;
}

// Use the point_cost from the workflow if not provided
if ($points_used <= 0) {
    $points_used = $workflow['point_cost'];
}

$uploaded_file_path = '';

// Check if image is base64 encoded
if (strpos($image_url, 'data:image/') === 0) {
    // Save image to disk
    $upload_dir = 'uploads/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Failed to create directory: $upload_dir");
            echo json_encode(['error' => 'Failed to create upload directory']);
            exit;
        }
    }
    
    // Generate a unique filename
    $filename = uniqid() . '-' . $image_filename;
    $filepath = $upload_dir . $filename;
    
    try {
        // Extract the base64 content
        $base64_content = substr($image_url, strpos($image_url, ',') + 1);
        $decoded_image = base64_decode($base64_content);
        
        // Save to file
        if (file_put_contents($filepath, $decoded_image) === false) {
            error_log("Failed to write file: $filepath");
            echo json_encode(['error' => 'Failed to save image to disk']);
            exit;
        }
        
        $uploaded_file_path = $filepath;
        
        // Update the image URL to point to the saved file
        $image_url = $filepath;
        
        error_log("Image saved to: $filepath");
    } catch (Exception $e) {
        error_log("Exception saving image: " . $e->getMessage());
        echo json_encode(['error' => 'Exception saving image: ' . $e->getMessage()]);
        exit;
    }
} elseif (strpos($image_url, 'http') === 0) {
    // Keep remote URL as is
    error_log("Using remote URL: " . substr($image_url, 0, 50) . "...");
} else {
    // Assume it's a relative path
    error_log("Using relative path: $image_url");
}

// Save generation to database
try {
    $insert_query = "INSERT INTO user_generations (user_id, workflow_id, workflow_name, category, image_url, filename, points_used, save_to_gallery, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($insert_query);
    
    if (!$stmt) {
        error_log("Prepare statement failed: " . $conn->error);
        echo json_encode(['error' => 'Database prepare error: ' . $conn->error]);
        exit;
    }
    
    // Extract filename from path if necessary
    $filename = basename($image_url);
    $gallery_flag = $save_to_gallery ? 1 : 0;
    
    $stmt->bind_param("iissssis", $user_id, $workflow_id, $workflow['name'], $workflow['category'], 
                      $image_url, $filename, $points_used, $gallery_flag);
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Execute failed: " . $stmt->error);
        echo json_encode(['error' => 'Database execution error: ' . $stmt->error]);
        exit;
    }
    
    $generation_id = $stmt->insert_id;
    $stmt->close();
    
    // Update user's points
    $update_points_query = "UPDATE users SET points = points - ? WHERE id = ?";
    $stmt = $conn->prepare($update_points_query);
    $stmt->bind_param("ii", $points_used, $user_id);
    $stmt->execute();
    $stmt->close();
    
    // Get remaining points
    $points_query = "SELECT points FROM users WHERE id = ?";
    $stmt = $conn->prepare($points_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $points_result = $stmt->get_result();
    $points_data = $points_result->fetch_assoc();
    $stmt->close();
    
    $remaining_points = $points_data ? $points_data['points'] : 0;
    
    echo json_encode([
        'success' => true,
        'message' => 'Generation saved successfully',
        'file_path' => $uploaded_file_path,
        'image_url' => $image_url,
        'points_remaining' => $remaining_points,
        'save_to_gallery' => $save_to_gallery,
        'generation_id' => $generation_id
    ]);
} catch (Exception $e) {
    error_log("Exception in database operation: " . $e->getMessage());
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?> 