<?php
require_once 'includes/db.php';
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: user/login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

// Check if ComfyUI is available
function isComfyUIAvailable() {
    $ch = curl_init('http://127.0.0.1:8188');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    return (empty($error) && $httpCode >= 200 && $httpCode < 300);
}

$comfyui_available = isComfyUIAvailable();

$id = (int)$_GET['id'];
$result = $conn->query("SELECT * FROM workflows WHERE id = $id");
$workflow = $result->fetch_assoc();

if (!$workflow) {
    header('Location: index.php');
    exit;
}

// Check user generation limits and points
$user_id = $_SESSION['user_id'];
$user_query = "SELECT usage_count, usage_limit, points FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user_result = $stmt->get_result();
$user_data = $user_result->fetch_assoc();
$stmt->close();

$usage_limit_reached = false;
if ($user_data && $user_data['usage_limit'] > 0 && $user_data['usage_count'] >= $user_data['usage_limit']) {
    $usage_limit_reached = true;
}

$not_enough_points = false;
if ($user_data && $workflow['point_cost'] > $user_data['points']) {
    $not_enough_points = true;
}

$inputs = json_decode($workflow['inputs'], true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="theme-color" content="#9c42f5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="description" content="Generate AI content with <?php echo htmlspecialchars($workflow['name']); ?> - ByteBrain">
    <title><?php echo htmlspecialchars($workflow['name']); ?> - ByteBrain</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <style>
        /* Critical CSS for initial render */
        :root {
            --glass-bg: rgba(30, 30, 40, 0.7);
            --glass-border: rgba(255, 255, 255, 0.1);
            --glass-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
            --accent-color: rgba(138, 43, 226, 0.8);
            --bytebrain-gradient-start: #9c42f5;
            --bytebrain-gradient-end: #5d7bf7;
        }
        
        .text-gradient {
            background: linear-gradient(90deg, var(--bytebrain-gradient-start) 0%, var(--bytebrain-gradient-end) 100%);
            -webkit-background-clip: text;
            color: transparent;
            background-clip: text;
        }
        
        body {
            background-color: #121212;
            color: #e0e0e0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        /* Add conditional loading for animations */
        @media (prefers-reduced-motion: no-preference) {
            body {
                background-image: 
                    radial-gradient(circle at 25% 25%, rgba(138, 43, 226, 0.1) 0%, transparent 50%),
                    radial-gradient(circle at 75% 75%, rgba(25, 118, 210, 0.1) 0%, transparent 50%);
            }
        }
        
        .glass {
            background: var(--glass-bg);
            border-radius: 16px;
            box-shadow: var(--glass-shadow);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid var(--glass-border);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        
        .glass:hover {
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
        }
        
        .glass-btn {
            background: rgba(80, 80, 120, 0.4);
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
            border: 1px solid var(--glass-border);
            transition: background 0.2s ease;
        }
        
        .glass-btn:hover {
            background: rgba(100, 100, 140, 0.5);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .glass-form input, .glass-form select, .glass-form textarea {
            background: rgba(20, 20, 30, 0.6);
            border: 1px solid var(--glass-border);
            color: white;
        }
        
        .glass-form input:focus, .glass-form select:focus, .glass-form textarea:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 2px rgba(138, 43, 226, 0.3);
        }
        
        .progress-bar-bg {
            background: rgba(50, 50, 70, 0.3);
        }
        
        .progress-bar {
            background: linear-gradient(to right, rgba(79, 70, 229, 0.6), rgba(138, 43, 226, 0.8));
            transition: width 0.5s ease-out;
        }
        
        .preview-area {
            background: rgba(20, 20, 30, 0.4);
            min-height: 300px;
        }
        
        /* Loading skeleton */
        .skeleton {
            background: linear-gradient(90deg, rgba(255,255,255,0.05) 25%, rgba(255,255,255,0.1) 50%, rgba(255,255,255,0.05) 75%);
            background-size: 200% 100%;
            animation: skeleton-loading 1.5s infinite;
        }
        
        @keyframes skeleton-loading {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
    </style>
</head>
<body>
    <div class="min-h-screen">
        <header class="glass-navbar">
            <div class="container mx-auto px-4 max-w-7xl">
                <div class="flex justify-between h-16 items-center">
                    <div class="flex items-center">
                        <a href="index.php" class="text-blue-400 text-2xl font-bold flex items-center">
                            <span class="text-gradient">ByteBrain</span>
                        </a>
                    </div>
                    <div class="flex items-center">
                        <a href="index.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">
                            <svg class="w-4 h-4 inline" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                            </svg>
                            Back
                        </a>
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <span class="text-gray-300 hidden sm:inline mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                            <a href="user/profile.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">Profile</a>
                            <a href="user/logout.php" class="glass-btn text-white px-3 py-1 rounded-md">Logout</a>
                        <?php else: ?>
                            <a href="user/login.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">Login</a>
                            <a href="user/register.php" class="glass-btn text-white px-3 py-1 rounded-md">Register</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <main class="container mx-auto px-4 py-8 max-w-7xl">
            <?php if (!$comfyui_available): ?>
            <div class="glass p-4 mb-6 border-l-4 border-red-500 border-opacity-50 text-red-200">
                <p class="font-bold">ComfyUI Service Offline</p>
                <p>The image generation service is currently offline. Your request will be queued and processed when the service is back online.</p>
            </div>
            <?php endif; ?>
            
            <div class="glass p-6 md:p-10 mb-8">
                <div class="flex flex-col md:flex-row md:items-center">
                    <div class="md:w-2/3">
                        <h1 class="text-3xl font-bold mb-4"><?php echo htmlspecialchars($workflow['name']); ?></h1>
                        <p class="text-gray-300 mb-6"><?php echo htmlspecialchars($workflow['description'] ?? 'Generate amazing content with this workflow'); ?></p>
                        <?php if ($usage_limit_reached): ?>
                        <div class="glass p-4 mb-6 border-l-4 border-yellow-500 border-opacity-50 text-yellow-200">
                            <p class="font-bold">Usage limit reached</p>
                            <p>You have reached your daily usage limit. Please try again tomorrow or upgrade your plan.</p>
                        </div>
                        <?php endif; ?>
                            
                        <?php if ($not_enough_points): ?>
                        <div class="glass p-4 mb-6 border-l-4 border-yellow-500 border-opacity-50 text-yellow-200">
                            <p class="font-bold">Not enough points</p>
                            <p>You need <?php echo $workflow['point_cost']; ?> points to use this workflow, but you only have <?php echo $user_data['points']; ?> points.</p>
                            <p class="mt-2"><a href="user/profile.php" class="underline">Get more points</a></p>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="md:w-1/3 flex justify-center mt-6 md:mt-0">
                        <div class="workflow-preview">
                            <div class="rounded-lg shadow-lg overflow-hidden">
                                <?php if (!empty($workflow['demo_image'])): ?>
                                <img src="assets/images/demos/<?php echo htmlspecialchars($workflow['demo_image']); ?>" 
                                     alt="<?php echo htmlspecialchars($workflow['name']); ?>" class="w-full h-48 object-cover">
                                <?php else: ?>
                                <div class="gradient-bg-<?php echo $id % 5 + 1; ?> h-48 flex items-center justify-center">
                                    <svg class="w-20 h-20 text-white opacity-50" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="glass p-5">
                    <form id="generateForm" class="glass-form space-y-3" <?php echo ($usage_limit_reached || $not_enough_points) ? 'disabled' : ''; ?>>
                        <input type="hidden" name="workflow_point_cost" id="workflow_point_cost" value="<?php echo $workflow['point_cost']; ?>">
                        <?php
                        foreach ($inputs as $key => $input) {
                            echo "<div>";
                            echo "<label class='block text-sm font-medium text-gray-300 mb-1'>" . htmlspecialchars($input['label']) . "</label>";
                            
                            switch ($input['type']) {
                                case 'text':
                                    echo "<input type='text' name='$key' value='" . htmlspecialchars($input['default']) . "' 
                                          class='w-full p-2 border border-opacity-20 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500' required " . 
                                          ($usage_limit_reached ? 'disabled' : '') . ">";
                                    break;
                                case 'textarea':
                                    echo "<textarea name='$key' rows='3' 
                                          class='w-full p-2 border border-opacity-20 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500' required " .
                                          ($usage_limit_reached ? 'disabled' : '') . ">" . 
                                          htmlspecialchars($input['default']) . "</textarea>";
                                    break;
                                case 'number':
                                    echo "<input type='number' name='$key' value='" . htmlspecialchars($input['default']) . "' 
                                          class='w-full p-2 border border-opacity-20 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500' required " .
                                          ($usage_limit_reached ? 'disabled' : '') . ">";
                                    break;
                                case 'select':
                                    echo "<select name='$key' class='w-full p-2 border border-opacity-20 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500' required " .
                                          ($usage_limit_reached ? 'disabled' : '') . ">";
                                    // Add options based on the field type
                                    if ($key === 'sampler_name') {
                                        $options = ['euler', 'euler_ancestral', 'heun', 'dpm_2', 'dpm_2_ancestral', 'lms', 'dpm_fast', 'dpm_adaptive', 'dpmpp_2s', 'dpmpp_sde', 'ddim'];
                                    } elseif ($key === 'scheduler') {
                                        $options = ['normal', 'karras', 'exponential'];
                                    }
                                    foreach ($options as $option) {
                                        $selected = ($option === $input['default']) ? 'selected' : '';
                                        echo "<option value='$option' $selected>" . ucfirst($option) . "</option>";
                                    }
                                    echo "</select>";
                                    break;
                                default:
                                    echo "<input type='text' name='$key' value='" . htmlspecialchars($input['default']) . "' 
                                          class='w-full p-2 border border-opacity-20 rounded-md focus:ring-2 focus:ring-purple-500 focus:border-purple-500' required " .
                                          ($usage_limit_reached ? 'disabled' : '') . ">";
                            }
                            echo "</div>";
                        }
                        ?>
                        <button type="submit" id="generate-btn" class="w-full glass-btn bg-blue-600 bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 transition-colors"
                               <?php echo ($usage_limit_reached || $not_enough_points) ? 'disabled style="background-color: rgba(147, 197, 253, 0.4); cursor: not-allowed;"' : ''; ?>>
                            Generate
                        </button>
                    </form>
                </div>

                <div class="glass p-5">
                    <h2 class="text-xl font-semibold text-white mb-3">Preview</h2>
                    <div id="preview" class="aspect-square preview-area rounded-md flex items-center justify-center">
                        <div class="text-center">
                            <p class="text-gray-400">Your generated content will appear here</p>
                            <p class="text-gray-500 text-sm mt-2">Click "Generate" to start</p>
                        </div>
                    </div>
                    <div id="loading" class="hidden mt-4">
                        <div class="flex items-center mb-2">
                            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-400 mr-3"></div>
                            <p class="text-gray-300" id="loading-text">Generating your content...</p>
                        </div>
                        <div class="w-full progress-bar-bg rounded-full h-2.5 mt-2 hidden" id="progress-bar-container">
                            <div class="progress-bar h-2.5 rounded-full" id="progress-bar" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- Error details (hidden by default) -->
                    <div id="error-details" class="hidden mt-4 p-4 glass rounded-md border border-red-500 border-opacity-30">
                        <h3 class="text-red-300 font-medium">Error Details</h3>
                        <pre class="mt-2 text-sm text-red-200 whitespace-pre-wrap" id="error-message"></pre>
                    </div>

                    <!-- Success details (hidden by default) -->
                    <div id="success-details" class="hidden mt-4">
                        <div class="flex flex-wrap gap-2">
                            <button id="download-btn" class="glass-btn bg-green-600 bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 transition-colors">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 013 3h10a3 3 0 013-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download
                            </button>
                            <button id="save-btn" class="glass-btn bg-blue-600 bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 transition-colors">
                                <svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg>
                                Save to Gallery
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <footer class="glass-footer py-6 mt-6">
            <div class="container mx-auto px-4 text-center">
                <div class="flex justify-center items-center mb-4">
                    <span class="text-xl font-bold text-gradient">ByteBrain</span>
                </div>
                <p class="text-gray-400 text-sm">&copy; <?php echo date('Y'); ?> ByteBrain. All rights reserved.</p>
            </div>
        </footer>
    </div>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="assets/js/error-handler.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize app immediately, jQuery is already loaded
        initApp();
        
        function initApp() {
            let imagePollingTimer = null;
            let currentPromptId = null;
            let currentGeneratedImage = null;
            
            const generateBtn = document.getElementById('generate-btn');
            
            $('#generateForm').submit(function(e) {
                e.preventDefault();
                
                // Display loading state
                $('#loading').removeClass('hidden');
                $('#error-details').addClass('hidden');
                
                // Collect form data
                const formData = new FormData(this);
                
                // Add workflow ID if it's not already in the form
                if (!formData.has('workflow_id')) {
                    formData.append('workflow_id', <?php echo $id; ?>);
                }
                
                // Debug: Log the form data before processing
                console.log('Form data before processing:');
                for (const [key, value] of formData.entries()) {
                    console.log(`${key}: ${value}`);
                }
                
                // Ensure inputs are properly formatted
                const inputData = {};
                for (const [key, value] of formData.entries()) {
                    if (key !== 'workflow_id' && key !== 'workflow_point_cost') {
                        inputData[key] = value;
                    }
                }
                
                // Debug: Log the input data
                console.log('Input data being sent to API:', inputData);
                
                // Add a timestamp to prevent caching
                inputData['_timestamp'] = Date.now();
                
                // Remove individual input fields and add them as a single inputs object
                for (const key in inputData) {
                    formData.delete(key);
                }
                formData.append('inputs', JSON.stringify(inputData));
                
                // Add a cache-busting parameter to prevent ComfyUI from returning cached results
                formData.append('cache_buster', Math.random().toString(36).substring(2, 15));
                
                // Disable the generate button
                generateBtn.disabled = true;
                generateBtn.textContent = 'Generating...';
                
                <?php if (!$comfyui_available): ?>
                // ComfyUI is offline, show a message about offline queuing
                setTimeout(function() {
                    $('#loading').addClass('hidden');
                    $('#preview').html(`
                        <div class="p-4 border border-yellow-500 border-opacity-30 rounded-md bg-yellow-900 bg-opacity-20 text-yellow-200">
                            <h3 class="font-bold mb-2">Request Queued</h3>
                            <p>The image generation service (ComfyUI) is currently offline.</p>
                            <p class="mt-2">Your request has been queued and will be processed when the service is back online.</p>
                            <p class="mt-2">You will receive a notification when your image is ready.</p>
                        </div>
                    `);
                    generateBtn.disabled = false;
                    generateBtn.textContent = 'Generate';
                }, 2000);
                
                // Still submit the data for queuing
                $.ajax({
                    url: 'api.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        console.log("Request queued for processing when ComfyUI is back online");
                    },
                    error: function(xhr, status, error) {
                        console.log("Error queueing request");
                    }
                });
                
                return false;
                <?php else: ?>
                // ComfyUI is online, proceed with normal request
                $.ajax({
                    url: 'api.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.error) {
                            showError(response.error);
                            
                            // If we have a prompt_id, we might be able to poll for progress
                            if (response.prompt_id) {
                                currentPromptId = response.prompt_id;
                                startPollingForProgress();
                            }
                        } else if (response.success && response.images && response.images.length > 0) {
                            // New response format - success!
                            const image = response.images[0];
                            showSuccess(image);
                        } else if (response.output && response.output.images) {
                            // Standard ComfyUI API response format
                            const imageUrl = 'http://localhost:8188/view?filename=' + response.output.images[0].filename;
                            showSuccess({url: imageUrl, filename: response.output.images[0].filename});
                        } else if (response.url) {
                            // Direct URL response
                            showSuccess({url: response.url, filename: 'image.png'});
                        } else if (response.image) {
                            // Image data response
                            showSuccess({url: response.image, filename: 'image.png'});
                        } else if (response.result && response.result[0] && response.result[0].data) {
                            // Original expected format
                            showSuccess({url: response.result[0].data, filename: 'image.png'});
                        } else {
                            // If we can't find the image, display the raw response
                            showError('Response received but couldn\'t find image data.');
                            $('#error-message').text(JSON.stringify(response, null, 2));
                        }
                    },
                    error: function(xhr, status, error) {
                        try {
                            // Use our error handler utility
                            const errorResult = processAjaxError(xhr, status, error);
                            showError(errorResult.errorMessage);
                            $('#error-message').text(errorResult.errorDetails);
                        } catch (e) {
                            // Process any errors in the error handler
                            const displayE = processError(e);
                            showError('Failed to process server response');
                            $('#error-message').text('Error: ' + displayE + '\nResponse: ' + xhr.responseText);
                        }
                    },
                    complete: function() {
                        // Don't hide loading if we're polling for progress
                        if (!currentPromptId) {
                            $('#loading').addClass('hidden');
                        }
                        // Re-enable the generate button
                        generateBtn.disabled = false;
                        generateBtn.textContent = 'Generate';
                    }
                });
                <?php endif; ?>
            });
            
            function showError(errorMessage) {
                // Use our error processing function
                let displayError = processError(errorMessage);
                
                $('#loading').addClass('hidden');
                $('#error-details').removeClass('hidden').html(displayError);
                
                // Re-enable the generate button
                generateBtn.disabled = false;
                generateBtn.textContent = 'Generate';
                
                // Clear any existing polling
                if (imagePollingTimer) {
                    clearInterval(imagePollingTimer);
                    imagePollingTimer = null;
                }
            }
            
            function showSuccess(image) {
                currentGeneratedImage = image;
                
                // Create a new image element to preload the image
                const img = new Image();
                img.onload = function() {
                    // Once loaded, update the preview
                    $('#preview').html(`<img src="${image.url}" class="max-w-full rounded-md" alt="Generated image">`);
                    $('#success-details').removeClass('hidden');
                    
                    // Save the generation and update usage
                    saveGeneration(image, false);
                };
                img.onerror = function() {
                    $('#preview').html(`<p class="text-red-500">Error loading image</p>`);
                };
                
                // Start loading the image
                img.src = image.url;
                
                // Hide loading UI
                $('#loading').addClass('hidden');
            }
            
            function saveGeneration(image, saveToGallery = false) {
                const pointCost = $('#workflow_point_cost').val() || 0;
                
                $.ajax({
                    url: 'save_generation.php',
                    method: 'POST',
                    data: {
                        workflow_id: <?php echo $id; ?>,
                        image_url: image.url,
                        image_filename: image.filename,
                        points_used: pointCost,
                        save_to_gallery: saveToGallery
                    },
                    dataType: 'json'
                });
            }
            
            // Add save button click handler
            $('#save-btn').click(function() {
                if (!currentGeneratedImage) return;
                
                const button = this;
                // Show saving indicator
                $(button).prop('disabled', true).html('<span class="inline-block animate-pulse">Saving...</span>');
                
                saveGeneration(currentGeneratedImage, true);
                
                // Show success after a brief delay
                setTimeout(function() {
                    $(button).html('<svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg> Saved!');
                    setTimeout(function() {
                        $(button).prop('disabled', false).html('<svg class="w-4 h-4 inline-block mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"></path></svg> Save to Gallery');
                    }, 2000);
                }, 1000);
            });
            
            // Add download button click handler
            $('#download-btn').click(function() {
                if (!currentGeneratedImage) return;
                
                // Create a temporary link to download the image
                const a = document.createElement('a');
                a.href = currentGeneratedImage.url;
                a.download = currentGeneratedImage.filename || 'generated-image.png';
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
            });
            
            function startPollingForProgress() {
                if (!currentPromptId) return;
                
                $('#progress-bar-container').removeClass('hidden');
                $('#loading-text').text('Processing image...');
                
                // Set a maximum polling time (5 minutes = 300000ms)
                const maxPollingTime = 300000;
                const startTime = Date.now();
                
                imagePollingTimer = setInterval(function() {
                    // Check if we've been polling too long
                    if (Date.now() - startTime > maxPollingTime) {
                        showError('Timed out waiting for image generation after 5 minutes');
                        clearInterval(imagePollingTimer);
                        imagePollingTimer = null;
                        currentPromptId = null;
                        return;
                    }
                    
                    $.ajax({
                        url: 'check_progress.php',
                        method: 'GET',
                        data: {
                            prompt_id: currentPromptId
                        },
                        dataType: 'json',
                        success: function(response) {
                            if (response.error) {
                                showError(response.error);
                                clearInterval(imagePollingTimer);
                                imagePollingTimer = null;
                                currentPromptId = null;
                                return;
                            }
                            
                            if (response.progress) {
                                const percent = Math.round(response.progress * 100);
                                $('#progress-bar').css('width', percent + '%');
                                $('#loading-text').text(`Processing image... ${percent}%`);
                            }
                            
                            if (response.status === 'success' && response.images && response.images.length > 0) {
                                showSuccess(response.images[0]);
                                clearInterval(imagePollingTimer);
                                imagePollingTimer = null;
                                currentPromptId = null;
                            }
                        },
                        error: function(xhr, status, error) {
                            // If there's a server error, don't immediately stop polling
                            if (xhr.status === 500) {
                                $('#loading-text').text('Server busy, still waiting for image...');
                            } 
                            // If we get too many errors, give up
                            else if (xhr.status === 0 || xhr.status >= 400) {
                                $('#loading-text').text('Connection issue, retrying...');
                            }
                        }
                    });
                }, 3000); // Check every 3 seconds
            }
        }
    });
    </script>
    <script src="assets/js/mobile-app.js"></script>
</body>
</html>