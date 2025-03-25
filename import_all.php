<?php
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'admin/workflow_functions.php';

// Directory containing workflow files
$workflow_dir = 'workfollowapi/';

// Array to store all workflow files with their categories
$categorized_workflows = [];

// Get JSON files from main directory (uncategorized)
$workflow_files = glob($workflow_dir . '*.json');
foreach ($workflow_files as $file_path) {
    $categorized_workflows[] = [
        'path' => $file_path,
        'category' => 'Uncategorized'
    ];
}

// Get all category folders
$directories = array_filter(glob($workflow_dir . '*'), 'is_dir');

// Add files from each category folder
foreach ($directories as $dir) {
    $category_name = basename($dir);
    $category_files = glob($dir . '/*.json');
    
    foreach ($category_files as $file_path) {
        $categorized_workflows[] = [
            'path' => $file_path,
            'category' => $category_name
        ];
    }
}

echo "<h1>Importing Workflows</h1>";
echo "<pre>";

$imported_count = 0;
$errors = [];

// Process each workflow file
foreach ($categorized_workflows as $workflow_info) {
    $file_path = $workflow_info['path'];
    $category = $workflow_info['category'];
    $filename = basename($file_path);
    $name = pathinfo($filename, PATHINFO_FILENAME);
    
    echo "Processing: " . $filename . " (Category: " . $category . ")\n";
    
    // Read the workflow file
    $jsonContent = file_get_contents($file_path);
    if (!$jsonContent) {
        $errors[] = "Could not read file: {$filename}";
        echo "Error: Could not read file\n";
        continue;
    }
    
    // Parse the workflow to extract inputs
    $inputs = parseWorkflowInputs($jsonContent);
    
    if (empty($inputs)) {
        $errors[] = "No inputs found in workflow: {$filename}";
        echo "Warning: No inputs found in this workflow\n";
    }
    
    // Copy the file to the uploads directory
    $new_filename = uniqid() . '_' . $filename;
    $target = UPLOAD_DIR . $new_filename;
    
    if (!copy($file_path, $target)) {
        $errors[] = "Failed to copy file: {$filename}";
        echo "Error: Failed to copy file\n";
        continue;
    }
    
    // Generate a description
    $description = "Imported workflow from {$category}/{$filename}";
    
    // Store in the database
    $inputsJson = json_encode($inputs);
    
    // Check if workflow already exists with this name
    $stmt = $conn->prepare("SELECT id FROM workflows WHERE name = ? AND category = ?");
    $stmt->bind_param("ss", $name, $category);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        // Workflow already exists, update it
        $row = $result->fetch_assoc();
        $id = $row['id'];
        $stmt = $conn->prepare("UPDATE workflows SET description = ?, api_file = ?, inputs = ? WHERE id = ?");
        $stmt->bind_param("sssi", $description, $new_filename, $inputsJson, $id);
        
        if ($stmt->execute()) {
            $imported_count++;
            echo "Updated existing workflow\n";
        } else {
            $errors[] = "Database error updating workflow: {$filename}: " . $stmt->error;
            echo "Error: Database error: " . $stmt->error . "\n";
        }
    } else {
        // New workflow, insert it
        $stmt = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $name, $description, $new_filename, $inputsJson, $category);
        
        if ($stmt->execute()) {
            $imported_count++;
            echo "Successfully imported\n";
        } else {
            $errors[] = "Database error for {$filename}: " . $stmt->error;
            echo "Error: Database error: " . $stmt->error . "\n";
        }
    }
    
    $stmt->close();
    echo "\n";
}

echo "Import Summary:\n";
echo "Successfully imported {$imported_count} workflows\n";

if (!empty($errors)) {
    echo "\nErrors:\n";
    foreach ($errors as $error) {
        echo "- {$error}\n";
    }
}

echo "</pre>";
echo "<p><a href='index.php'>Go to Homepage</a> | <a href='admin/index.php'>Go to Admin Panel</a></p>";
?> 