<?php
require_once 'includes/db.php';

// Step 1: Delete all existing workflows
$conn->query("DELETE FROM workflows");
echo "All existing workflows deleted.<br>";

// Step 2: Add the first workflow (SDXL)
$name1 = "SDXL Advanced";
$description1 = "Generate high-quality images using SDXL model with base and refiner";
$apiFile1 = "sdxl_simple_example.json";
$category1 = 1; // Image category
$pointCost1 = 20;

// Define inputs for SDXL workflow
$inputs1 = [
    "prompt" => [
        "label" => "Prompt",
        "type" => "textarea",
        "default" => "big boobs, full body no cloth, clear pussy, bikiny"
    ],
    "negative_prompt" => [
        "label" => "Negative Prompt",
        "type" => "textarea",
        "default" => "text, watermark"
    ],
    "seed" => [
        "label" => "Seed",
        "type" => "number",
        "default" => 822419662134058
    ],
    "steps" => [
        "label" => "Steps",
        "type" => "number",
        "default" => 25
    ],
    "cfg" => [
        "label" => "CFG Scale",
        "type" => "number",
        "default" => 8
    ],
    "width" => [
        "label" => "Width",
        "type" => "number",
        "default" => 1024
    ],
    "height" => [
        "label" => "Height",
        "type" => "number",
        "default" => 1024
    ]
];

$inputsJson1 = json_encode($inputs1);

// Insert SDXL workflow
$stmt1 = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
$stmt1->bind_param("ssssii", $name1, $description1, $apiFile1, $inputsJson1, $category1, $pointCost1);
$stmt1->execute();
echo "Added workflow: $name1<br>";

// Step 3: Add the second workflow (Small Photo)
$name2 = "Small Photo";
$description2 = "Generate smaller images with standard SD 1.5 model";
$apiFile2 = "smallphoto.json";
$category2 = 1; // Image category
$pointCost2 = 10;

// Define inputs for Small Photo workflow
$inputs2 = [
    "prompt" => [
        "label" => "Prompt",
        "type" => "textarea",
        "default" => "beautiful scenery nature glass bottle landscape, purple galaxy bottle"
    ],
    "negative_prompt" => [
        "label" => "Negative Prompt",
        "type" => "textarea",
        "default" => "text, watermark"
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

$inputsJson2 = json_encode($inputs2);

// Insert Small Photo workflow
$stmt2 = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
$stmt2->bind_param("ssssii", $name2, $description2, $apiFile2, $inputsJson2, $category2, $pointCost2);
$stmt2->execute();
echo "Added workflow: $name2<br>";

echo "<br>All workflows have been updated successfully. <a href='admin/index.php'>Go to admin panel</a> to see the changes.";
?>
