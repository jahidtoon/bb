<?php
require_once 'includes/db.php';

// Define workflow details
$name = "Basic Photo";
$description = "Generate a basic photo with customizable prompt and settings";
$apiFile = "smallphoto.json";
$category = "Image Generation";
$pointCost = 10;

// Define inputs based on the workflow JSON
$inputs = [
    "prompt" => [
        "label" => "Prompt",
        "type" => "textarea",
        "default" => "beautiful scenery nature glass bottle landscape, purple galaxy bottle"
    ],
    "negative_prompt" => [
        "label" => "Negative Prompt",
        "type" => "textarea",
        "default" => "text, watermark, low quality"
    ],
    "seed" => [
        "label" => "Seed",
        "type" => "number",
        "default" => 420698849380725
    ],
    "steps" => [
        "label" => "Steps",
        "type" => "number",
        "default" => 20
    ],
    "cfg" => [
        "label" => "CFG Scale",
        "type" => "number",
        "default" => 8
    ],
    "sampler_name" => [
        "label" => "Sampler",
        "type" => "select",
        "default" => "euler"
    ],
    "scheduler" => [
        "label" => "Scheduler",
        "type" => "select",
        "default" => "normal"
    ],
    "width" => [
        "label" => "Width",
        "type" => "number",
        "default" => 512
    ],
    "height" => [
        "label" => "Height",
        "type" => "number",
        "default" => 512
    ]
];

// Convert inputs to JSON
$inputsJson = json_encode($inputs);

// Check if workflow already exists
$checkStmt = $conn->prepare("SELECT id FROM workflows WHERE name = ?");
$checkStmt->bind_param("s", $name);
$checkStmt->execute();
$result = $checkStmt->get_result();

if ($result->num_rows > 0) {
    // Update existing workflow
    $row = $result->fetch_assoc();
    $workflowId = $row['id'];
    
    $updateStmt = $conn->prepare("UPDATE workflows SET description = ?, api_file = ?, inputs = ?, category = ?, point_cost = ? WHERE id = ?");
    $updateStmt->bind_param("sssiii", $description, $apiFile, $inputsJson, $category, $pointCost, $workflowId);
    
    if ($updateStmt->execute()) {
        echo "Workflow 'Basic Photo' updated successfully with ID: " . $workflowId;
    } else {
        echo "Error updating workflow: " . $conn->error;
    }
    
    $updateStmt->close();
} else {
    // Insert new workflow
    $insertStmt = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
    $insertStmt->bind_param("sssssi", $name, $description, $apiFile, $inputsJson, $category, $pointCost);
    
    if ($insertStmt->execute()) {
        $workflowId = $conn->insert_id;
        echo "Workflow 'Basic Photo' added successfully with ID: " . $workflowId;
    } else {
        echo "Error adding workflow: " . $conn->error;
    }
    
    $insertStmt->close();
}

$checkStmt->close();
$conn->close();

echo "\nWorkflow has been added to the database. You can now use it from the main page.";
?>
