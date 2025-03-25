<?php
// Include database connection
require_once 'includes/db.php';
require_once 'includes/config.php';

// Function to display messages
function showMessage($message, $isError = false) {
    $class = $isError ? 'error' : 'success';
    echo "<div class='$class'>$message</div>";
}

// Delete all existing workflows
try {
    // First, get all workflow files to delete them
    $stmt = $conn->query("SELECT api_file FROM workflows");
    $files = $stmt->fetch_all(MYSQLI_ASSOC);
    
    // Delete the physical files
    foreach ($files as $file) {
        $filePath = UPLOAD_DIR . $file['api_file'];
        if (file_exists($filePath)) {
            unlink($filePath);
            echo "Deleted file: " . $file['api_file'] . "<br>";
        }
    }
    
    // Delete all records from the workflows table
    $result = $conn->query("DELETE FROM workflows");
    
    if ($result) {
        showMessage("All workflows have been deleted successfully.");
    } else {
        showMessage("Error deleting workflows: " . $conn->error, true);
    }
} catch (Exception $e) {
    showMessage("Exception: " . $e->getMessage(), true);
}

// Add the two new workflows from workfollowapi folder
try {
    // Workflow 1: SDXL Simple Example
    $name1 = "SDXL Advanced";
    $description1 = "Generate high-quality images using SDXL model with base and refiner";
    $apiFile1 = "sdxl_simple_example.json";
    $category1 = 1; // Assuming 1 is for Images category
    $pointCost1 = 20;
    
    // Define inputs for SDXL workflow
    $inputs1 = [
        "prompt" => [
            "type" => "text",
            "label" => "Prompt",
            "default" => "beautiful scenery, high quality, detailed",
            "required" => true
        ],
        "negative_prompt" => [
            "type" => "text",
            "label" => "Negative Prompt",
            "default" => "text, watermark, low quality",
            "required" => false
        ],
        "width" => [
            "type" => "number",
            "label" => "Width",
            "default" => 1024,
            "min" => 512,
            "max" => 1024,
            "step" => 64,
            "required" => true
        ],
        "height" => [
            "type" => "number",
            "label" => "Height",
            "default" => 1024,
            "min" => 512,
            "max" => 1024,
            "step" => 64,
            "required" => true
        ],
        "seed" => [
            "type" => "number",
            "label" => "Seed",
            "default" => -1,
            "required" => false
        ],
        "steps" => [
            "type" => "number",
            "label" => "Steps",
            "default" => 25,
            "min" => 10,
            "max" => 50,
            "required" => true
        ],
        "cfg" => [
            "type" => "number",
            "label" => "CFG Scale",
            "default" => 8,
            "min" => 1,
            "max" => 20,
            "step" => 0.5,
            "required" => true
        ]
    ];
    
    // Workflow 2: Small Photo
    $name2 = "Small Photo";
    $description2 = "Generate smaller images with standard SD 1.5 model";
    $apiFile2 = "smallphoto.json";
    $category2 = 1; // Assuming 1 is for Images category
    $pointCost2 = 10;
    
    // Define inputs for Small Photo workflow
    $inputs2 = [
        "prompt" => [
            "type" => "text",
            "label" => "Prompt",
            "default" => "beautiful scenery nature",
            "required" => true
        ],
        "negative_prompt" => [
            "type" => "text",
            "label" => "Negative Prompt",
            "default" => "text, watermark",
            "required" => false
        ],
        "width" => [
            "type" => "number",
            "label" => "Width",
            "default" => 512,
            "min" => 256,
            "max" => 768,
            "step" => 64,
            "required" => true
        ],
        "height" => [
            "type" => "number",
            "label" => "Height",
            "default" => 512,
            "min" => 256,
            "max" => 768,
            "step" => 64,
            "required" => true
        ],
        "seed" => [
            "type" => "number",
            "label" => "Seed",
            "default" => -1,
            "required" => false
        ],
        "steps" => [
            "type" => "number",
            "label" => "Steps",
            "default" => 20,
            "min" => 10,
            "max" => 50,
            "required" => true
        ],
        "cfg" => [
            "type" => "number",
            "label" => "CFG Scale",
            "default" => 8,
            "min" => 1,
            "max" => 20,
            "step" => 0.5,
            "required" => true
        ]
    ];
    
    // Convert inputs to JSON
    $inputsJson1 = json_encode($inputs1);
    $inputsJson2 = json_encode($inputs2);
    
    // Insert the first workflow
    $stmt1 = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt1->bind_param("sssiii", $name1, $description1, $apiFile1, $inputsJson1, $category1, $pointCost1);
    
    if ($stmt1->execute()) {
        $workflowId1 = $conn->insert_id;
        showMessage("Workflow '$name1' added successfully with ID: $workflowId1");
    } else {
        showMessage("Error adding workflow '$name1': " . $conn->error, true);
    }
    
    // Insert the second workflow
    $stmt2 = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt2->bind_param("sssiii", $name2, $description2, $apiFile2, $inputsJson2, $category2, $pointCost2);
    
    if ($stmt2->execute()) {
        $workflowId2 = $conn->insert_id;
        showMessage("Workflow '$name2' added successfully with ID: $workflowId2");
    } else {
        showMessage("Error adding workflow '$name2': " . $conn->error, true);
    }
    
    echo "<p>All workflows have been reset. <a href='admin/index.php'>Go to admin panel</a></p>";
    
} catch (Exception $e) {
    showMessage("Exception: " . $e->getMessage(), true);
}
?>

<style>
    body {
        font-family: Arial, sans-serif;
        margin: 20px;
        line-height: 1.6;
    }
    .success {
        background-color: #d4edda;
        color: #155724;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
    }
    .error {
        background-color: #f8d7da;
        color: #721c24;
        padding: 10px;
        margin-bottom: 10px;
        border-radius: 4px;
    }
    a {
        color: #007bff;
        text-decoration: none;
    }
    a:hover {
        text-decoration: underline;
    }
</style>
