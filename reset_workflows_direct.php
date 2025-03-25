<?php
require_once 'includes/db.php';

// Step 1: Delete all existing workflows
$conn->query("DELETE FROM workflows");
echo "All existing workflows deleted.<br>";

// Step 2: Add workflows from JSON files
$jsonFiles = [
    'sdxl_simple_example.json' => [
        'name' => 'SDXL Advanced',
        'description' => 'Generate high-quality images using SDXL model',
        'category' => 1,
        'point_cost' => 20
    ],
    'smallphoto.json' => [
        'name' => 'Small Photo',
        'description' => 'Generate smaller images with standard SD 1.5 model',
        'category' => 1,
        'point_cost' => 10
    ]
];

foreach ($jsonFiles as $jsonFile => $workflowInfo) {
    // Read the JSON file
    $jsonContent = file_get_contents('workfollowapi/' . $jsonFile);
    $jsonData = json_decode($jsonContent, true);
    
    // Extract inputs from the JSON workflow
    $inputs = [];
    
    // For SDXL workflow
    if ($jsonFile === 'sdxl_simple_example.json') {
        // Get prompt from node 6
        if (isset($jsonData['6']['inputs']['text'])) {
            $inputs['prompt'] = [
                'label' => 'Prompt',
                'type' => 'textarea',
                'default' => $jsonData['6']['inputs']['text']
            ];
        }
        
        // Get negative prompt from node 7
        if (isset($jsonData['7']['inputs']['text'])) {
            $inputs['negative_prompt'] = [
                'label' => 'Negative Prompt',
                'type' => 'textarea',
                'default' => $jsonData['7']['inputs']['text']
            ];
        }
        
        // Get width and height from node 5
        if (isset($jsonData['5']['inputs']['width'])) {
            $inputs['width'] = [
                'label' => 'Width',
                'type' => 'number',
                'default' => $jsonData['5']['inputs']['width']
            ];
        }
        
        if (isset($jsonData['5']['inputs']['height'])) {
            $inputs['height'] = [
                'label' => 'Height',
                'type' => 'number',
                'default' => $jsonData['5']['inputs']['height']
            ];
        }
        
        // Get seed, steps, cfg from node 10
        if (isset($jsonData['10']['inputs']['noise_seed'])) {
            $inputs['seed'] = [
                'label' => 'Seed',
                'type' => 'number',
                'default' => $jsonData['10']['inputs']['noise_seed']
            ];
        }
        
        if (isset($jsonData['10']['inputs']['steps'])) {
            $inputs['steps'] = [
                'label' => 'Steps',
                'type' => 'number',
                'default' => $jsonData['10']['inputs']['steps']
            ];
        }
        
        if (isset($jsonData['10']['inputs']['cfg'])) {
            $inputs['cfg'] = [
                'label' => 'CFG Scale',
                'type' => 'number',
                'default' => $jsonData['10']['inputs']['cfg']
            ];
        }
        
        if (isset($jsonData['10']['inputs']['sampler_name'])) {
            $inputs['sampler_name'] = [
                'label' => 'Sampler',
                'type' => 'select',
                'default' => $jsonData['10']['inputs']['sampler_name']
            ];
        }
        
        if (isset($jsonData['10']['inputs']['scheduler'])) {
            $inputs['scheduler'] = [
                'label' => 'Scheduler',
                'type' => 'select',
                'default' => $jsonData['10']['inputs']['scheduler']
            ];
        }
    }
    // For Small Photo workflow
    else if ($jsonFile === 'smallphoto.json') {
        // Get prompt from node 6
        if (isset($jsonData['6']['inputs']['text'])) {
            $inputs['prompt'] = [
                'label' => 'Prompt',
                'type' => 'textarea',
                'default' => $jsonData['6']['inputs']['text']
            ];
        }
        
        // Get negative prompt from node 7
        if (isset($jsonData['7']['inputs']['text'])) {
            $inputs['negative_prompt'] = [
                'label' => 'Negative Prompt',
                'type' => 'textarea',
                'default' => $jsonData['7']['inputs']['text']
            ];
        }
        
        // Get width and height from node 5
        if (isset($jsonData['5']['inputs']['width'])) {
            $inputs['width'] = [
                'label' => 'Width',
                'type' => 'number',
                'default' => $jsonData['5']['inputs']['width']
            ];
        }
        
        if (isset($jsonData['5']['inputs']['height'])) {
            $inputs['height'] = [
                'label' => 'Height',
                'type' => 'number',
                'default' => $jsonData['5']['inputs']['height']
            ];
        }
        
        // Get seed, steps, cfg from node 3
        if (isset($jsonData['3']['inputs']['seed'])) {
            $inputs['seed'] = [
                'label' => 'Seed',
                'type' => 'number',
                'default' => $jsonData['3']['inputs']['seed']
            ];
        }
        
        if (isset($jsonData['3']['inputs']['steps'])) {
            $inputs['steps'] = [
                'label' => 'Steps',
                'type' => 'number',
                'default' => $jsonData['3']['inputs']['steps']
            ];
        }
        
        if (isset($jsonData['3']['inputs']['cfg'])) {
            $inputs['cfg'] = [
                'label' => 'CFG Scale',
                'type' => 'number',
                'default' => $jsonData['3']['inputs']['cfg']
            ];
        }
        
        if (isset($jsonData['3']['inputs']['sampler_name'])) {
            $inputs['sampler_name'] = [
                'label' => 'Sampler',
                'type' => 'select',
                'default' => $jsonData['3']['inputs']['sampler_name']
            ];
        }
        
        if (isset($jsonData['3']['inputs']['scheduler'])) {
            $inputs['scheduler'] = [
                'label' => 'Scheduler',
                'type' => 'select',
                'default' => $jsonData['3']['inputs']['scheduler']
            ];
        }
    }
    
    // Convert inputs to JSON
    $inputsJson = json_encode($inputs);
    
    // Insert workflow into database
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
        echo "Added workflow: " . $workflowInfo['name'] . " (using " . $jsonFile . ")<br>";
    } else {
        echo "Error adding workflow: " . $conn->error . "<br>";
    }
}

echo "<br>All workflows have been reset. Now there are only two workflows based on the JSON files in workfollowapi folder.";
?>
