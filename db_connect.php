<?php

if (file_exists(__DIR__ . '/.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
}

function getDatabaseConnection() {
    $servername = $_ENV['DB_HOST'] ?? 'localhost';
    $username = $_ENV['DB_USER'] ?? 'root';
    $password = $_ENV['DB_PASSWORD'] ?? '';
    $dbname = $_ENV['DB_NAME'] ?? 'video_call';
    $port = $_ENV['DB_PORT'] ?? 3306;

    error_log("DB Connection - Host: $servername | Port: $port | User: $username | Database: $dbname");

    try {
        $conn = new mysqli($servername, $username, $password, $dbname, $port);
    } catch (Exception $e) {
        error_log("mysqli connection error: " . $e->getMessage());
        throw new Exception("Database connection failed: " . $e->getMessage() . " (Trying to connect to: $servername:$port)");
    }

    if ($conn->connect_error) {
        $error = "Database connection failed: " . $conn->connect_error . " (Host: $servername:$port)";
        error_log($error);
        throw new Exception($error);
    }

    $sql = "CREATE TABLE IF NOT EXISTS rooms (
        id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        room_id VARCHAR(30) NOT NULL UNIQUE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )";
    if (!$conn->query($sql)) {
        error_log("Error creating table: " . $conn->error);
    }

    return $conn;
}