<?php
// Database configuration
$host = "localhost";
$username = "abhiruchischool_db"; // Change as per your server
$password = "MB[0ge(Ob+hF"; // Change as per your server
$database = "abhiruchischool_db";

// Create connection
$conn = new mysqli($host, $username, $password, $database);

// Check connection
if ($conn->connect_error) {
    // For debugging, you can enable this:
    // die("Connection failed: " . $conn->connect_error);
    // But for production, return JSON
    header('Content-Type: application/json');
    echo json_encode(array('success' => false, 'message' => 'Database connection failed'));
    exit();
}

// Set charset to UTF-8
$conn->set_charset("utf8mb4");

// Function to sanitize input
if (!function_exists('sanitize_input')) {
    function sanitize_input($data, $conn) {
        if (empty($data)) {
            return $data;
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $data = $conn->real_escape_string($data);
        return $data;
    }
}

?>