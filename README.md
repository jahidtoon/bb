# AI Generator Website

A PHP-based website that integrates with ComfyUI to generate AI-powered images and videos using prebuilt workflows.

## Features

- Clean, modern UI optimized for mobile devices
- Prebuilt workflow management through admin panel
- Dynamic form generation based on workflow inputs
- Real-time preview of generated content
- Secure file upload handling
- Responsive design using Tailwind CSS

## Prerequisites

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP (or similar local development environment)
- ComfyUI running locally at http://127.0.0.1:8188

## Installation

1. Clone this repository to your XAMPP's htdocs directory:
   ```bash
   cd C:\xampp\htdocs
   git clone <repository-url> ai-website
   ```

2. Create the database and tables:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the `database.sql` file from the project root

3. Configure the application:
   - Open `includes/config.php`
   - Update database credentials if needed
   - Ensure ComfyUI URL is correct

4. Set up file permissions:
   - Make sure the `uploads` directory is writable by the web server
   - On Windows, right-click the folder → Properties → Security → Edit → Add → Everyone → Full Control

5. Start the required services:
   - Start Apache and MySQL in XAMPP
   - Start ComfyUI locally

## Usage

1. Access the website:
   - Open http://localhost/ai-website in your browser

2. Admin Panel:
   - Access http://localhost/ai-website/admin
   - Default credentials:
     - Username: admin
     - Password: admin123

3. Adding Workflows:
   - Log in to the admin panel
   - Click "Add New Workflow"
   - Fill in the workflow details
   - Upload the ComfyUI workflow JSON file
   - Define the input fields in JSON format

4. Generating Content:
   - Browse available workflows on the homepage
   - Click "Generate Now" on your chosen workflow
   - Fill in the required inputs
   - Click "Generate" to create content

## Security Notes

- Change the default admin password after first login
- Implement proper authentication in production
- Use HTTPS in production
- Regularly backup your database and uploaded files
- Keep ComfyUI and all dependencies updated

## Development

The project structure is organized as follows:

```
ai-website/
├── admin/              # Admin panel files
├── assets/            # CSS, JS, images
├── uploads/           # Workflow API files
├── includes/          # Config and database
├── index.php          # Homepage
├── generate.php       # Generation page
└── api.php           # ComfyUI API integration
```

## Contributing

1. Fork the repository
2. Create your feature branch
3. Commit your changes
4. Push to the branch
5. Create a new Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details. 