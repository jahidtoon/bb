<?php
require_once 'includes/db.php';

// Define the workflow data
$name = "Basic Photo";
$description = "Generate a basic photo with customizable prompt and settings";
$api_file = "smallphoto.json";
$category = "Image Generation";
$point_cost = 10;

// Define the inputs for the workflow
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
    ]
];

// Convert inputs to JSON
$inputs_json = json_encode($inputs);

// Check if the workflow already exists
$check_sql = "SELECT id FROM workflows WHERE name = ?";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $name);
$check_stmt->execute();
$result = $check_stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing workflow
    $row = $result->fetch_assoc();
    $workflow_id = $row['id'];
    
    $update_sql = "UPDATE workflows SET description = ?, api_file = ?, inputs = ?, category = ?, point_cost = ? WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("sssiii", $description, $api_file, $inputs_json, $category, $point_cost, $workflow_id);
    
    if ($update_stmt->execute()) {
        echo "Workflow 'Basic Photo' updated successfully with ID: " . $workflow_id;
    } else {
        echo "Error updating workflow: " . $conn->error;
    }
    
    $update_stmt->close();
} else {
    // Insert new workflow
    $insert_sql = "INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param("sssssi", $name, $description, $api_file, $inputs_json, $category, $point_cost);
    
    if ($insert_stmt->execute()) {
        $workflow_id = $conn->insert_id;
        echo "Workflow 'Basic Photo' added successfully with ID: " . $workflow_id;
    } else {
        echo "Error adding workflow: " . $conn->error;
    }
    
    $insert_stmt->close();
}

// Make sure the workflow JSON file exists in the correct location
$workflow_dir = "workfollowapi";
if (!file_exists($workflow_dir)) {
    mkdir($workflow_dir, 0777, true);
}

// Check if the file already exists
if (!file_exists($workflow_dir . "/" . $api_file)) {
    // Copy the workflow JSON file to the workfollowapi directory
    $workflow_json = file_get_contents("workfollowapi/smallphoto.json");
    file_put_contents($workflow_dir . "/" . $api_file, $workflow_json);
    echo "\nWorkflow JSON file saved to " . $workflow_dir . "/" . $api_file;
} else {
    echo "\nWorkflow JSON file already exists at " . $workflow_dir . "/" . $api_file;
}

$check_stmt->close();
$conn->close();
?>
