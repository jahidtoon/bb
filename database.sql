-- Create database
CREATE DATABASE IF NOT EXISTS ai_website_db;
USE ai_website_db;

-- Create workflows table
CREATE TABLE IF NOT EXISTS workflows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    api_file VARCHAR(255) NOT NULL,
    inputs TEXT NOT NULL,
    category VARCHAR(100) DEFAULT 'Uncategorized',
    point_cost INT DEFAULT 10,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create users table with expanded user management
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(100),
    is_admin TINYINT(1) DEFAULT 0,
    status ENUM('active', 'disabled') DEFAULT 'active',
    points INT DEFAULT 100,
    usage_limit INT DEFAULT 50,
    usage_count INT DEFAULT 0,
    last_login DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Create user_generations table to track usage history
CREATE TABLE IF NOT EXISTS user_generations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    workflow_id INT NOT NULL,
    workflow_name VARCHAR(255),
    category VARCHAR(100),
    image_url TEXT,
    filename VARCHAR(255),
    inputs TEXT,
    points_used INT DEFAULT 0,
    save_to_gallery TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (workflow_id) REFERENCES workflows(id) ON DELETE CASCADE
);

-- Insert default admin user (password: admin123)
INSERT INTO users (username, password, email, is_admin, status) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 1, 'active'); 