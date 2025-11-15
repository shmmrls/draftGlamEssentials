<?php
require_once('../includes/config.php');
require_once('email_sender.php');
session_start();

// Set JSON response header
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/email_error.log');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login first.']);
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user role
$user_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if (!$user_data || $user_data['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['order_id']) || empty($input['order_id'])) {
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int) $input['order_id'];

// Call the reusable email function
try {
    $result = sendOrderEmailNotification($conn, $order_id);
    echo json_encode($result);
} catch (Exception $e) {
    error_log("Email send exception: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while sending email: ' . $e->getMessage()
    ]);
}
?>