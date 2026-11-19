<?php
// Database configuration
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'inventory_system';

// Create connection
try {
    $pdo = new PDO("mysql:host=$host;dbname=$database", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    // If database doesn't exist, create it
    if ($e->getCode() == 1049) {
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $database");
        $pdo->exec("USE $database");
        createTables($pdo);
    } else {
        die("Connection failed: " . $e->getMessage());
    }
}

// Create tables function
function createTables($pdo) {
    $sql = [];
    
    $sql[] = "CREATE TABLE IF NOT EXISTS products (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        price_frw DECIMAL(12,2) DEFAULT 0,
        stock_quantity INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $sql[] = "CREATE TABLE IF NOT EXISTS clients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(255) NOT NULL,
        phone VARCHAR(20),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    
    $sql[] = "CREATE TABLE IF NOT EXISTS sales (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT,
        client_id INT,
        quantity_sold INT NOT NULL,
        total_price_frw DECIMAL(12,2) NOT NULL,
        sale_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id),
        FOREIGN KEY (client_id) REFERENCES clients(id)
    )";
    
    $sql[] = "CREATE TABLE IF NOT EXISTS stock_history (
        id INT PRIMARY KEY AUTO_INCREMENT,
        product_id INT,
        change_type ENUM('add_stock', 'sell') NOT NULL,
        quantity_change INT NOT NULL,
        new_quantity INT NOT NULL,
        change_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id)
    )";
    
    foreach ($sql as $query) {
        try {
            $pdo->exec($query);
        } catch(PDOException $e) {
            // Ignore table already exists errors
            if (strpos($e->getMessage(), 'already exists') === false) {
                error_log("Table creation error: " . $e->getMessage());
            }
        }
    }
}

// Ensure tables exist
createTables($pdo);

// Session start if needed
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>