# AI Generator Website Notes

## Project Overview
- This is a PHP-based website that integrates with ComfyUI to generate AI-powered images and videos using prebuilt workflows.
- The website allows users to select workflows and generate content by filling in required inputs.
- It includes an admin panel for managing workflows, users, and system settings.
- Workflows are organized by categories for better organization.
- User registration and management system with points-based usage control.
- Security features to protect user data and prevent unauthorized access.
- Optimized for performance with modern web techniques for faster page loading and smoother user experience.
- Progressive Web App (PWA) capabilities for offline access and mobile installation.

## System Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP (local development environment)
- ComfyUI running locally at http://127.0.0.1:8188
- GD library for PHP (for icon generation)

## Database Setup
- Database name: `ai_website_db`
- Tables:
  - `workflows`: Stores workflow configurations with categories and point costs
  - `users`: Stores user credentials, points, and usage limits
  - `user_generations`: Stores history of user-generated content with metadata
  - `user_favorites`: Stores user-saved favorite workflows for quick access
  - `notifications`: Stores system and user-specific notifications
  - `api_logs`: Tracks API usage for rate limiting and debugging

## File Structure
```
ai-website/
├── admin/              # Admin panel files
│   ├── index.php            # Admin dashboard
│   ├── workflow_functions.php # Functions for workflow management
│   ├── dashboard.php        # Admin dashboard interface
│   ├── users.php            # User management interface
│   ├── cleanup_uploads.php  # Utility to clean up old uploads
│   ├── check_workflow_files.php # Utility to check and fix workflow files
│   ├── import_workflows.php # Import workflow functionality
│   ├── point_transactions.php # User point transaction management
│   └── login.php            # Admin login page
├── assets/            # CSS, JS, images
│   ├── css/                # Stylesheet files
│   ├── js/                 # JavaScript files
│   └── images/             # Image assets including PWA icons
│       └── demos/          # Demo images for workflows
├── css/               # Shared CSS styles
│   └── main.css            # Centralized CSS for consistent UI
├── uploads/           # Workflow API files & uploaded content
├── includes/          # Configuration files
│   ├── config.php          # System configuration
│   ├── db.php              # Database connection
│   ├── security.php        # Security functions
│   └── api_limiter.php     # API rate limiting functionality
├── user/              # User-related files
│   ├── register.php        # User registration
│   ├── login.php           # User login
│   ├── profile.php         # User profile management
│   ├── history.php         # Generation history & gallery view
│   ├── favorites.php       # User-saved favorite workflows
│   ├── notifications.php   # User notification center
│   └── logout.php          # User logout
├── index.php          # Homepage - displays available workflows by category
├── generate.php       # Page for generating content with a workflow
├── api.php            # ComfyUI API integration with error handling
├── save_generation.php # API endpoint to save generation history
├── check_generate.php # Checks generation status
├── check_progress.php # Monitors generation progress
├── check_workflow.php # Verifies workflow file format
├── database.sql       # Database schema
├── import_all.php     # Utility for importing workflows
├── service-worker.js  # PWA service worker for offline functionality
├── manifest.json      # PWA manifest for installation
├── offline.html       # Offline page for PWA
├── create_pwa_icons.php # Script to generate PWA icons
├── create_pwa_icons.html # Guide for setting up PWA icons
├── create_background.php # Script to generate background image
└── README.md          # Project documentation
```

## Configuration Details
- DB host: localhost
- DB user: root
- DB password: blank
- ComfyUI URL: http://127.0.0.1:8188
- Upload directory: [projectroot]/uploads/

## Admin Panel
- URL: http://localhost/ai-website/admin
- Admin logins:
  - Default admin:
    - Username: admin
    - Password: admin123
  - Additional admin:
    - Email: jahidultoon@gmail.com
    - Role: Administrator with full access
- Features:
  - Workflow management (add, edit, delete)
  - Category management
  - User management (view, edit, disable accounts)
  - Usage statistics
  - Cleanup tools for old uploads
  - Workflow file verification and repair tools
  - API usage monitoring and rate limit configuration
  - Featured content management for public gallery
  - Point transaction management

## Point System
- Each user has a points balance (default: 100 points)
- Each workflow has a point cost (default: 10 points)
- Points are deducted when a user generates content
- Admin can adjust user points and workflow costs
- Points provide flexible usage control beyond simple limits
- Daily/weekly bonus points for active users
- Referral system for earning additional points

## User Management
- Registration system with email validation
- Login/logout functionality with secure password handling
- User profiles with personal details and point balance
- Generation history with downloadable content
- Usage statistics and remaining points
- Visual progress bars for points and usage limits
- Improved profile page with user avatar and stats cards
- Two-factor authentication option
- Social media login integration (Google, Facebook)
- User favorites system to save preferred workflows

## Main Workflow
1. Users register for an account and log in
2. Users browse available workflows by category on the homepage
3. Users click "Generate Now" on their chosen workflow
4. On generate.php page, users fill in required inputs
5. When "Generate" is clicked, the form data is sent to api.php
6. api.php processes the request and sends it to ComfyUI
7. The website shows real-time progress during generation
8. When complete, the generated image is displayed with download options
9. Generated content is saved to user's history and points are deducted
10. Users receive notifications when generations are complete

## Key Features
- Clean, modern UI with Tailwind CSS and glass-morphism design
- Dynamic form generation based on workflow inputs
- Real-time generation progress monitoring
- Point-based usage system for flexible user limits
- Error handling and troubleshooting support
- Responsive design for mobile and desktop devices
- Secure file handling and user authentication
- Gallery-style masonry grid layout for viewing user generations
- Category filtering in image gallery
- Performance optimization with critical CSS loading and reduced animations
- Shared CSS file (main.css) for consistent styling across all pages
- Lazy loading and content visibility optimizations for faster page loads
- Dark/light mode toggle for user preference
- Notification system for completed generations
- Social sharing capabilities for generated content
- Favorites system for saving preferred workflows
- PWA support for mobile installation and offline access
- Image optimization with WebP and AVIF formats
- Stylish background with ByteBrain branding colors

## Workflow Configuration
Workflows are stored in the database with these key fields:
- name: Display name for the workflow
- description: Detailed description
- category: Category of the workflow
- api_file: Path to the workflow JSON file
- inputs: JSON configuration for required inputs
- point_cost: Cost in points to use this workflow
- featured: Boolean flag for featured workflows
- complexity: Indicator of workflow processing complexity
- avg_completion_time: Average time to complete generation
- demo_image: Sample image to showcase the workflow

## User Data Structure
User records include:
- username: Unique username for login
- password: Securely hashed password
- email: User's email address
- full_name: User's full name
- points: Current point balance
- usage_limit: Maximum number of generations allowed
- usage_count: Number of generations used
- status: Account status (active/disabled)
- created_at: Account creation date
- last_login: Timestamp of last login
- theme_preference: User's preferred UI theme (light/dark)
- notification_preferences: JSON of notification settings
- referral_code: Unique code for referral system
- is_admin: Boolean flag indicating administrator privileges (0 = regular user, 1 = admin)

## API Integration
The website communicates with ComfyUI using:
- Direct HTTP requests to ComfyUI API
- JSON workflow structures with dynamic input replacement
- Progress polling for long-running generations
- Image retrieval and storage after generation
- Automatic error handling and file recovery if workflow files are missing
- Rate limiting to prevent API abuse
- Queuing system for high-traffic periods

## Security Features
- Secure authentication with session management
- Password hashing with bcrypt
- Input validation and sanitization
- Protection against SQL injection
- CSRF token validation
- Secure file upload handling
- Two-factor authentication option
- Rate limiting for login attempts
- IP-based blocking for suspicious activity
- Security headers (CSP, X-XSS-Protection)
- Role-based access control (admin vs regular users)

## Image Gallery Features
- Masonry grid layout for visually appealing display of generated images
- Category filtering to organize images
- Hover effects with image information overlay
- Responsive design that works on mobile and desktop
- Gallery/All filter options
- Pagination with improved navigation
- Optimized image loading with lazy loading and skeleton placeholders
- Intersection Observer implementation for performance
- Featured section for highlighted user creations
- Social sharing capabilities
- Full-screen view option with zoom controls
- Download in multiple formats (JPG, PNG, WebP)

## PWA Features
- Installable on mobile and desktop devices
- Offline access to previously viewed content
- Dedicated offline.html page with helpful information
- Push notifications for completed generations
- Background synchronization for queued operations
- Responsive design adapting to all screen sizes
- Fast loading with cached resources
- App-like experience with smooth transitions
- Custom app icons in multiple sizes (192x192, 512x512)
- Custom shortcut icons for gallery and profile pages
- Manifest with app information and theme colors
- Service worker with strategic caching:
  - Network-first for HTML content
  - Cache-first for static assets
  - Network-only for API requests
- Background sync for offline requests

## PWA Assets
- icon-192x192.png: Main app icon for smaller displays
- icon-512x512.png: Main app icon for larger displays
- gallery-icon.png: Icon for gallery shortcut
- profile-icon.png: Icon for profile shortcut
- background.jpg: Website background image
- Utility scripts to generate these assets:
  - create_pwa_icons.php: PHP script to generate PWA icons
  - create_pwa_icons.html: Guide for setting up PWA icons
  - create_background.php: Script to generate background image

## Performance Optimizations
- Centralized CSS in main.css file for consistent styling and better caching
- Critical CSS inlining for above-the-fold content
- Lazy loading of images with Intersection Observer
- Reduced motion for users who prefer less animation
- Content visibility optimization for offscreen content
- Query optimization for faster database operations
- Preconnect to CDN resources for faster stylesheet loading
- Skeleton loading states for better perceived performance
- Deferred loading of non-critical JavaScript
- Image format optimization with WebP and AVIF
- HTTP/2 server push for critical resources
- Database indexing for frequently queried fields
- Caching strategies for static content
- Relative paths in service worker for better portability

## Error Handling
- Robust API error handling for failed requests
- Automatic workflow file replacement if files are missing
- Admin utility to check and fix missing workflow files
- Graceful error messages for users
- Logging of errors for troubleshooting
- Real-time error notifications for administrators
- Automated recovery procedures for common issues
- Offline page for users without internet connectivity

## Common Issues & Troubleshooting
- ComfyUI connection failures: Check if ComfyUI is running at the configured URL
- Workflow parsing errors: Validate JSON format of workflow files
- Input validation errors: Check input types match workflow requirements
- Image generation timeouts: Increase PHP timeout limits in config
- User registration issues: Verify email server configuration
- Missing workflow files: Use the admin check_workflow_files.php utility
- Rate limiting issues: Check and adjust limits in api_limiter.php
- PWA icon issues: Use create_pwa_icons.php or create_pwa_icons.html

## Development Notes
- Add new workflows through the admin panel or import_workflows.php
- Test workflows thoroughly before making them public
- Back up the database and uploaded files regularly
- Keep ComfyUI updated to latest version
- Maintain proper user access controls
- Use cleanup_uploads.php periodically to remove unused files
- Use check_workflow_files.php to identify and fix missing workflow files
- Monitor API usage with the admin dashboard tools
- Admin privileges can be assigned via the admin panel or by using the admin_update.php utility script
- For PWA updates, modify manifest.json and service-worker.js

## Restoration Process
1. Set up XAMPP environment
2. Import database.sql to create the database
3. Configure includes/config.php with correct settings
4. Ensure ComfyUI is running locally
5. Generate PWA assets using provided scripts
6. Access the site at http://localhost/ai-website

## Suggested Implementations
- Implement referral system for users to earn bonus points
- Launch public gallery of featured generations with user consent
- Add interactive workflow preview feature before generation
- Create AI-guided workflow recommendations based on user history
- Implement batch processing for multiple generations
- Add style transfer between user uploads and AI models
- Enable image editing features for post-generation refinement
- Implement progressive enhancement for slower connections
- Add accessibility features (ARIA labels, keyboard navigation)
- Create image comparison tool for before/after effects
- Build community features like comments and ratings
- Integrate with cloud storage providers (Google Drive, Dropbox)
- Develop user achievements and gamification elements
- Implement webhook notifications for third-party integrations
- Create backup and restore functionality for user content
- Expand PWA capabilities with more offline functionality
- Implement IndexedDB for offline data storage 