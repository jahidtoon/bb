<?php
require_once 'includes/db.php';
session_start();

$is_logged_in = isset($_SESSION['user_id']);

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

// Get all categories and workflows in a single query for better performance
$all_workflows = [];
$all_categories = [];

$sql = "SELECT id, name, category, description, inputs, point_cost, demo_image FROM workflows ORDER BY category, name";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $category = $row['category'] ?? 'Uncategorized';
        if (!isset($all_workflows[$category])) {
            $all_workflows[$category] = [];
            $all_categories[] = $category;
        }
        $all_workflows[$category][] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="description" content="ByteBrain - Create amazing AI content with various workflows">
    <meta name="theme-color" content="#9c42f5">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>ByteBrain - AI Generator</title>
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="css/main.css">
    <link rel="manifest" href="manifest.json">
    <link rel="apple-touch-icon" href="images/icon-192x192.png">
    <style>
        /* Critical CSS for immediate rendering */
        .hero-section {
            min-height: 60vh;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            text-align: center;
            position: relative;
            overflow: hidden;
            z-index: 1;
        }
        
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 30% 50%, rgba(156, 66, 245, 0.15), transparent 50%),
                        radial-gradient(circle at 70% 50%, rgba(93, 123, 247, 0.1), transparent 50%);
            z-index: -1;
        }

        h1 {
            font-size: 2.5rem;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            background: linear-gradient(90deg, #9c42f5 0%, #5d7bf7 100%);
            -webkit-background-clip: text;
            color: transparent;
        }
        
        .workflow-card {
            height: 100%;
            display: flex;
            flex-direction: column;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .workflow-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }
        
        .workflow-card .card-body {
            flex: 1 1 auto;
        }
        
        /* Category styling */
        .category-section {
            content-visibility: auto;
            contain-intrinsic-size: 0 500px;
            margin-bottom: 2rem;
            position: relative;
        }
        
        .category-header {
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid rgba(156, 66, 245, 0.3);
            position: relative;
            display: flex;
            align-items: center;
        }
        
        .category-header::before {
            content: '';
            position: absolute;
            left: 0;
            bottom: -1px;
            width: 60px;
            height: 3px;
            background: linear-gradient(90deg, #9c42f5, #5d7bf7);
            border-radius: 3px;
        }
        
        .category-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #fff;
            margin-left: 0.5rem;
        }
        
        /* Card enhancements */
        .card-image-container {
            position: relative;
            overflow: hidden;
            height: 160px;
        }
        
        .card-image {
            background-size: cover;
            background-position: center;
            height: 100%;
            transition: transform 0.5s ease;
        }
        
        .workflow-card:hover .card-image {
            transform: scale(1.05);
        }
        
        .card-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(0deg, rgba(0,0,0,0.7) 0%, rgba(0,0,0,0.2) 50%, rgba(0,0,0,0) 100%);
        }
        
        /* Category nav */
        .category-nav {
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            margin-bottom: 2rem;
            padding-bottom: 0.5rem;
            display: flex;
            gap: 0.5rem;
        }
        
        .category-nav::-webkit-scrollbar {
            height: 4px;
        }
        
        .category-nav::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
        
        .category-nav::-webkit-scrollbar-thumb {
            background: rgba(156, 66, 245, 0.5);
            border-radius: 4px;
        }
        
        .category-nav-item {
            display: inline-block;
            padding: 0.5rem 1rem;
            background-color: rgba(20, 20, 40, 0.6);
            color: #ccc;
            border-radius: 9999px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            cursor: pointer;
        }
        
        .category-nav-item:hover, .category-nav-item.active {
            background-color: rgba(156, 66, 245, 0.3);
            color: white;
        }
        
        /* Premium badge */
        .premium-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: linear-gradient(45deg, #FFD700, #FFA500);
            color: #000;
            font-weight: bold;
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            z-index: 10;
            box-shadow: 0 2px 4px rgba(0,0,0,0.3);
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
                        <?php if(isset($_SESSION['user_id'])): ?>
                            <span class="text-gray-300 hidden sm:inline mr-4">Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'User'); ?></span>
                            <a href="user/profile.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">Profile</a>
                            <a href="user/history.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">History</a>
                            <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === 1): ?>
                                <a href="workflow_tools/" class="text-blue-300 hover:text-blue-100 px-4 py-2">Workflow Tools</a>
                            <?php endif; ?>
                            <a href="user/logout.php" class="glass-btn text-white px-3 py-1 rounded-md">Logout</a>
                    <?php else: ?>
                            <a href="user/login.php" class="text-blue-300 hover:text-blue-100 px-4 py-2">Login</a>
                            <a href="user/register.php" class="glass-btn text-white px-3 py-1 rounded-md">Register</a>
                    <?php endif; ?>
                    </div>
                </div>
            </div>
        </header>

        <?php if(!isset($_SESSION['user_id'])): ?>
        <div class="hero-section">
            <div class="glass p-6 md:p-10 max-w-3xl mx-auto backdrop-blur-lg">
                <h1 class="font-bold mb-4">Welcome to ByteBrain</h1>
                <p class="text-xl text-gray-300 mb-6">Transform your ideas into reality with our AI-powered workflows</p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="user/register.php" class="btn btn-primary">
                        Get Started
                    </a>
                    <a href="user/login.php" class="btn btn-outline">
                        Login
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <main class="container mx-auto px-4 py-8 max-w-7xl">
            <?php if(!isset($_SESSION['user_id'])): ?>
            <div class="glass p-4 mb-6 border-l-4 border-yellow-500 border-opacity-50 text-yellow-200">
                <p class="font-bold">Not logged in</p>
                <p>Please <a href="user/login.php" class="underline hover:text-yellow-100">login</a> or <a href="user/register.php" class="underline hover:text-yellow-100">register</a> to generate content.</p>
            </div>
            <?php elseif(isset($_SESSION['user_id']) && !$comfyui_available): ?>
            <div class="glass p-4 mb-6 border-l-4 border-red-500 border-opacity-50 text-red-200">
                <p class="font-bold">ComfyUI Service Offline</p>
                <p>The image generation service is currently offline. Generation requests will be queued and processed when the service is back online.</p>
            </div>
            <?php endif; ?>

            <div class="flex justify-between items-center mb-8">
                <h2 class="text-2xl font-bold text-white">Available Workflows</h2>
                <?php if(isset($_SESSION['user_id'])): ?>
                <div class="text-right text-gray-300">
                    <span class="inline-block px-3 py-1 glass rounded-full">
                        <svg class="w-4 h-4 inline-block mr-1" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z"></path>
                            <path d="M10 14a4 4 0 100-8 4 4 0 000 8z"></path>
                        </svg>
                        <span><?php echo htmlspecialchars($_SESSION['points'] ?? '0'); ?> Points</span>
                    </span>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if(count($all_categories) > 1): ?>
            <div class="category-nav">
                <div class="category-nav-item active" data-target="all">All</div>
                <?php foreach($all_categories as $category): 
                    if (!isset($all_workflows[$category]) || empty($all_workflows[$category])) continue;
                ?>
                <div class="category-nav-item" data-target="<?php echo htmlspecialchars(preg_replace('/[^a-z0-9]/', '-', strtolower($category))); ?>">
                    <?php echo htmlspecialchars($category); ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <?php 
            $displayed_categories = 0;
            foreach($all_categories as $category): 
                if (!isset($all_workflows[$category]) || empty($all_workflows[$category])) continue;
                $displayed_categories++;
                $category_id = htmlspecialchars(preg_replace('/[^a-z0-9]/', '-', strtolower($category)));
            ?>
                <div class="category-section" id="category-<?php echo $category_id; ?>">
                    <div class="category-header">
                        <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z"></path>
                        </svg>
                        <h3 class="category-title"><?php echo htmlspecialchars($category); ?></h3>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                        <?php foreach($all_workflows[$category] as $workflow): 
                            $isPremium = $workflow['point_cost'] > 100; // Arbitrary threshold for premium
                        ?>
                            <div class="glass workflow-card overflow-hidden">
                                <?php if($isPremium): ?>
                                <div class="premium-badge">PREMIUM</div>
                                <?php endif; ?>
                                <div class="card-image-container">
                                    <?php if (!empty($workflow['demo_image'])): ?>
                                    <div class="card-image" style="background-image: url('assets/images/demos/<?php echo htmlspecialchars($workflow['demo_image']); ?>');">
                                        <div class="card-overlay"></div>
                                    </div>
                                    <?php else: ?>
                                    <div class="card-image gradient-bg-<?php echo $workflow['id'] % 5 + 1; ?>">
                                        <div class="card-overlay"></div>
                                        <div class="card-image-icon">
                                            <svg class="w-12 h-12 text-white opacity-50" fill="currentColor" viewBox="0 0 20 20" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" d="M11.3 1.046A1 1 0 0112 2v5h4a1 1 0 01.82 1.573l-7 10A1 1 0 018 18v-5H4a1 1 0 01-.82-1.573l7-10a1 1 0 011.12-.38z" clip-rule="evenodd"></path>
                                            </svg>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4 card-body">
                                    <h4 class="text-lg font-semibold text-white mb-2"><?php echo htmlspecialchars($workflow['name']); ?></h4>
                                    
                                    <p class="text-gray-300 text-sm mb-3 overflow-hidden" style="display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                        <?php echo htmlspecialchars($workflow['description'] ?? 'Generate amazing content with this workflow'); ?>
                                    </p>
                                    
                        <?php
                                    $inputs = json_decode($workflow['inputs'], true);
                                    // Only show up to 3 tags to avoid cluttering the UI
                                    $input_tags = array_slice(array_keys($inputs ?? []), 0, 3);
                                    ?>
                                    <div class="flex flex-wrap gap-2 mb-3">
                                        <?php foreach($input_tags as $input): ?>
                                            <span class="inline-block bg-blue-900 bg-opacity-50 rounded px-2 py-1 text-xs text-blue-200">
                                                <?php echo htmlspecialchars($input); ?>
                                            </span>
                                        <?php endforeach; ?>
                                        <?php if(count($inputs ?? []) > 3): ?>
                                            <span class="inline-block bg-blue-900 bg-opacity-50 rounded px-2 py-1 text-xs text-blue-200">
                                                +<?php echo count($inputs) - 3; ?> more
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="text-sm text-gray-400 mb-4">
                                        <span class="inline-flex items-center">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path d="M10 18a8 8 0 100-16 8 8 0 000 16zm0-2a6 6 0 100-12 6 6 0 000 12z"></path>
                                                <path d="M10 14a4 4 0 100-8 4 4 0 000 8z"></path>
                                            </svg>
                                            <span class="<?php echo $isPremium ? 'text-yellow-300' : ''; ?>"><?php echo $workflow['point_cost']; ?> Points</span>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="px-4 pb-4">
                                    <?php if(isset($_SESSION['user_id'])): ?>
                                        <a href="generate.php?id=<?php echo $workflow['id']; ?>" class="w-full glass-btn <?php echo $isPremium ? 'bg-yellow-600' : 'bg-blue-600'; ?> bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                            </svg>
                                            Generate
                                        </a>
                                    <?php else: ?>
                                        <a href="user/login.php" class="w-full glass-btn bg-blue-600 bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 flex items-center justify-center">
                                            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path>
                                            </svg>
                                            Login to Generate
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <?php if ($displayed_categories === 0): ?>
                <div class="glass p-6 text-center">
                    <p class="text-gray-300">No workflows available at the moment.</p>
                    <?php if(isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                        <p class="mt-4">
                            <a href="admin/index.php" class="glass-btn bg-blue-600 bg-opacity-40 text-white py-2 px-4 rounded-md hover:bg-opacity-60 inline-block">
                                Add Workflows
                            </a>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
    
    <script>
    // Use Intersection Observer for lazy loading content
    document.addEventListener('DOMContentLoaded', function() {
        // Lazy loading for categories
        if ('IntersectionObserver' in window) {
            const categoryObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.style.opacity = '1';
                        categoryObserver.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '50px 0px',
                threshold: 0.1
            });
            
            document.querySelectorAll('.category-section').forEach(section => {
                section.style.opacity = '0';
                section.style.transition = 'opacity 0.5s ease';
                categoryObserver.observe(section);
            });
        }
        
        // Category navigation
        const categoryNavItems = document.querySelectorAll('.category-nav-item');
        categoryNavItems.forEach(item => {
            item.addEventListener('click', function() {
                const target = this.getAttribute('data-target');
                
                // Remove active class from all items
                categoryNavItems.forEach(navItem => {
                    navItem.classList.remove('active');
                });
                
                // Add active class to clicked item
                this.classList.add('active');
                
                // Show/hide categories
                if (target === 'all') {
                    document.querySelectorAll('.category-section').forEach(section => {
                        section.style.display = 'block';
                    });
                } else {
                    document.querySelectorAll('.category-section').forEach(section => {
                        if (section.id === 'category-' + target) {
                            section.style.display = 'block';
                        } else {
                            section.style.display = 'none';
                        }
                    });
                }
            });
        });
    });
    </script>
    <script src="assets/js/mobile-app.js"></script>
    <script>
        // Register the service worker
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', function() {
                navigator.serviceWorker.register('service-worker.js')
                    .then(function(registration) {
                        console.log('Service worker registered successfully');
                    })
                    .catch(function(error) {
                        console.log('Service worker registration failed:', error);
                    });
            });
        }
    </script>
</body>
</html> 