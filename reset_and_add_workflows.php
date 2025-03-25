<?php
require_once 'includes/db.php';
require_once 'includes/config.php';
require_once 'admin/workflow_functions.php';

// Step 1: Delete all existing workflows
echo "<h1>Resetting Workflows</h1>";
echo "<pre>";

// First get all workflow files to potentially delete them
$stmt = $conn->query("SELECT api_file FROM workflows");
$files = $stmt->fetch_all(MYSQLI_ASSOC);

// Delete all records from the database
$result = $conn->query("DELETE FROM workflows");
if ($result) {
    echo "All workflows have been deleted from the database.\n";
} else {
    echo "Error deleting workflows: " . $conn->error . "\n";
    exit;
}

// Step 2: Add the two new workflows

// Workflow files to add
$workflowFiles = [
    'sdxl_simple_example.json' => [
        'name' => 'SDXL Advanced',
        'description' => 'Generate high-quality images using SDXL model with base and refiner',
        'category' => 1, // Image category
        'point_cost' => 20
    ],
    'smallphoto.json' => [
        'name' => 'Small Photo',
        'description' => 'Generate smaller images with standard SD 1.5 model',
        'category' => 1, // Image category
        'point_cost' => 10
    ]
];

$importedCount = 0;

foreach ($workflowFiles as $jsonFile => $workflowInfo) {
    $filePath = 'workfollowapi/' . $jsonFile;
    
    // Check if the file exists
    if (!file_exists($filePath)) {
        echo "Error: File not found: {$filePath}\n";
        continue;
    }
    
    echo "Processing: {$jsonFile}\n";
    
    // Read the workflow file
    $jsonContent = file_get_contents($filePath);
    if (!$jsonContent) {
        echo "Error: Could not read file: {$jsonFile}\n";
        continue;
    }
    
    // Parse the workflow to extract inputs
    $inputs = parseWorkflowInputs($jsonContent);
    
    if (empty($inputs)) {
        echo "Warning: No inputs found in workflow: {$jsonFile}\n";
        // Use default inputs from the workflow info
        if ($jsonFile === 'sdxl_simple_example.json') {
            $inputs = [
                'prompt' => [
                    'type' => 'textarea',
                    'default' => 'big boobs, full body no cloth, clear pussy, bikiny',
                    'label' => 'Prompt'
                ],
                'negative_prompt' => [
                    'type' => 'textarea',
                    'default' => 'text, watermark',
                    'label' => 'Negative Prompt'
                ],
                'seed' => [
                    'type' => 'number',
                    'default' => '822419662134058',
                    'label' => 'Seed'
                ],
                'steps' => [
                    'type' => 'number',
                    'default' => '25',
                    'label' => 'Steps'
                ],
                'cfg' => [
                    'type' => 'number',
                    'default' => '8',
                    'label' => 'CFG Scale'
                ],
                'width' => [
                    'type' => 'number',
                    'default' => '1024',
                    'label' => 'Width'
                ],
                'height' => [
                    'type' => 'number',
                    'default' => '1024',
                    'label' => 'Height'
                ]
            ];
        } else if ($jsonFile === 'smallphoto.json') {
            $inputs = [
                'prompt' => [
                    'type' => 'textarea',
                    'default' => 'beautiful scenery nature glass bottle landscape, purple galaxy bottle',
                    'label' => 'Prompt'
                ],
                'negative_prompt' => [
                    'type' => 'textarea',
                    'default' => 'text, watermark',
                    'label' => 'Negative Prompt'
                ],
                'seed' => [
                    'type' => 'number',
                    'default' => '420698849380725',
                    'label' => 'Seed'
                ],
                'steps' => [
                    'type' => 'number',
                    'default' => '20',
                    'label' => 'Steps'
                ],
                'cfg' => [
                    'type' => 'number',
                    'default' => '8',
                    'label' => 'CFG Scale'
                ],
                'width' => [
                    'type' => 'number',
                    'default' => '512',
                    'label' => 'Width'
                ],
                'height' => [
                    'type' => 'number',
                    'default' => '512',
                    'label' => 'Height'
                ]
            ];
        }
    }
    
    // Convert inputs to JSON
    $inputsJson = json_encode($inputs);
    
    // Insert workflow into database using the existing API file path
    $stmt = $conn->prepare("INSERT INTO workflows (name, description, api_file, inputs, category, point_cost) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssii", 
        $workflowInfo['name'], 
        $workflowInfo['description'], 
        $jsonFile, 
        $inputsJson, 
        $workflowInfo['category'], 
        $workflowInfo['point_cost']
    );
    
    if ($stmt->execute()) {
        $importedCount++;
        echo "Successfully added workflow: " . $workflowInfo['name'] . "\n";
    } else {
        echo "Error adding workflow: " . $conn->error . "\n";
    }
}

echo "\nImport Summary:\n";
echo "Successfully added {$importedCount} workflows\n";
echo "</pre>";

echo "<p><a href='index.php'>Go to Homepage</a> | <a href='admin/index.php'>Go to Admin Panel</a></p>";
?>
