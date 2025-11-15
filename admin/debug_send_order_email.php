<?php
require_once('../includes/config.php');
session_start();

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'email_debug.log');

// Set JSON response header
header('Content-Type: application/json');

// Log function
function logDebug($message) {
    $timestamp = date('Y-m-d H:i:s');
    error_log("[$timestamp] $message\n", 3, 'email_debug.log');
}

logDebug("=== EMAIL SEND REQUEST STARTED ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    logDebug("ERROR: User not logged in or role not set");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

logDebug("User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['role']);

if ($_SESSION['role'] !== 'admin') {
    logDebug("ERROR: User is not admin");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
logDebug("Input received: " . json_encode($input));

if (!isset($input['order_id'])) {
    logDebug("ERROR: Order ID not provided");
    echo json_encode(['success' => false, 'message' => 'Order ID is required']);
    exit;
}

$order_id = (int) $input['order_id'];
logDebug("Processing order ID: $order_id");

// Fetch order details with customer email
$order_sql = "SELECT 
    o.order_id,
    o.transaction_id,
    o.customer_id,
    c.fullname as customer_name,
    c.address as customer_address,
    c.contact_no as customer_contact,
    o.shipping_name,
    o.shipping_address,
    o.shipping_contact,
    o.payment_method,
    o.payment_status,
    o.order_status,
    o.total_amount,
    o.order_date,
    u.email as customer_email
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN users u ON c.user_id = u.user_id
WHERE o.order_id = ?";

$order_stmt = $conn->prepare($order_sql);
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();
$order = $order_result->fetch_assoc();
$order_stmt->close();

if (!$order) {
    logDebug("ERROR: Order not found in database");
    echo json_encode(['success' => false, 'message' => 'Order not found']);
    exit;
}

logDebug("Order found - Customer: " . $order['customer_name']);
logDebug("Customer email: " . ($order['customer_email'] ?? 'NULL'));

if (empty($order['customer_email'])) {
    logDebug("ERROR: Customer email is empty");
    echo json_encode(['success' => false, 'message' => 'Customer email not found']);
    exit;
}

// Fetch order items
$items_sql = "SELECT 
    oi.order_item_id,
    oi.product_id,
    p.product_name,
    p.main_img_name,
    oi.quantity,
    oi.price,
    oi.subtotal
FROM order_items oi
JOIN products p ON oi.product_id = p.product_id
WHERE oi.order_id = ?";

$items_stmt = $conn->prepare($items_sql);
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items = $items_result->fetch_all(MYSQLI_ASSOC);
$items_stmt->close();

logDebug("Found " . count($items) . " items in order");

// Mailtrap SMTP Configuration - CORRECT CREDENTIALS
$mailtrap_host = 'sandbox.smtp.mailtrap.io';
$mailtrap_port = 2525;
$mailtrap_username = 'a87cd38542b37e'; // CORRECTED
$mailtrap_password = 'dd36e50f7ab566'; // CORRECTED
$from_email = 'orders@glamessentials.com';
$from_name = 'GlamEssentials';

logDebug("Connecting to SMTP: $mailtrap_host:$mailtrap_port");

// Test SMTP connection
$socket = @fsockopen($mailtrap_host, $mailtrap_port, $errno, $errstr, 30);
if (!$socket) {
    logDebug("ERROR: Failed to connect to SMTP server - $errstr ($errno)");
    echo json_encode(['success' => false, 'message' => "Connection failed: $errstr"]);
    exit;
}

logDebug("Connected to SMTP server");

// Read greeting
$response = fgets($socket, 515);
logDebug("Server greeting: " . trim($response));

// Send EHLO and read ALL response lines (CRITICAL FIX!)
fputs($socket, "EHLO " . ($_SERVER['SERVER_NAME'] ?? 'localhost') . "\r\n");
logDebug("Sent EHLO command");
do {
    $line = fgets($socket, 515);
    logDebug("EHLO response: " . trim($line));
} while (substr(trim($line), 0, 4) === '250-');
logDebug("EHLO complete");

// Send AUTH LOGIN
fputs($socket, "AUTH LOGIN\r\n");
$response = fgets($socket, 515);
logDebug("AUTH LOGIN response: " . trim($response));

if (strpos($response, '334') === false) {
    logDebug("ERROR: AUTH LOGIN command failed");
    fclose($socket);
    echo json_encode(['success' => false, 'message' => 'AUTH LOGIN failed']);
    exit;
}

// Send username
fputs($socket, base64_encode($mailtrap_username) . "\r\n");
$response = fgets($socket, 515);
logDebug("Username response: " . trim($response));

if (strpos($response, '334') === false) {
    logDebug("ERROR: Username not accepted");
    fclose($socket);
    echo json_encode(['success' => false, 'message' => 'Username authentication failed']);
    exit;
}

// Send password
fputs($socket, base64_encode($mailtrap_password) . "\r\n");
$response = fgets($socket, 515);
logDebug("Password response: " . trim($response));

if (strpos($response, '235') === false) {
    logDebug("ERROR: SMTP Authentication failed - " . trim($response));
    fclose($socket);
    echo json_encode(['success' => false, 'message' => 'SMTP authentication failed']);
    exit;
}

logDebug("SMTP authentication successful");

// Send MAIL FROM
fputs($socket, "MAIL FROM: <{$from_email}>\r\n");
$response = fgets($socket, 515);
logDebug("MAIL FROM response: " . trim($response));

// Send RCPT TO
$to_email = $order['customer_email'];
fputs($socket, "RCPT TO: <{$to_email}>\r\n");
$response = fgets($socket, 515);
logDebug("RCPT TO response: " . trim($response));

// Send DATA
fputs($socket, "DATA\r\n");
$response = fgets($socket, 515);
logDebug("DATA response: " . trim($response));

// Build email subject
$subject = "Order #{$order['order_id']} Status Update";
logDebug("Email subject: $subject");

// Build simple email body for testing
$items_html = '';
foreach ($items as $item) {
    $items_html .= htmlspecialchars($item['product_name']) . " (Qty: " . $item['quantity'] . ") - ₱" . number_format($item['subtotal'], 2) . "<br>";
}

$html_body = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'><title>Order Update</title></head>
<body style='font-family: Arial, sans-serif; padding: 20px;'>
    <h1>Order Update - #{$order['order_id']}</h1>
    <p>Hello " . htmlspecialchars($order['customer_name']) . ",</p>
    <p>Your order status has been updated.</p>
    <h3>Order Details:</h3>
    <p><strong>Order ID:</strong> #{$order['order_id']}</p>
    <p><strong>Transaction ID:</strong> " . htmlspecialchars($order['transaction_id']) . "</p>
    <p><strong>Order Status:</strong> {$order['order_status']}</p>
    <p><strong>Payment Status:</strong> {$order['payment_status']}</p>
    <h3>Items:</h3>
    <p>$items_html</p>
    <h3>Total Amount: ₱" . number_format($order['total_amount'], 2) . "</h3>
    <p>Thank you for your order!</p>
</body>
</html>
";

// Send email message
$message = "From: {$from_name} <{$from_email}>\r\n";
$message .= "To: {$order['customer_name']} <{$to_email}>\r\n";
$message .= "Subject: {$subject}\r\n";
$message .= "MIME-Version: 1.0\r\n";
$message .= "Content-Type: text/html; charset=UTF-8\r\n";
$message .= "\r\n";
$message .= $html_body;
$message .= "\r\n.\r\n";

fputs($socket, $message);
$response = fgets($socket, 515);
logDebug("Send response: " . trim($response));

// Send QUIT
fputs($socket, "QUIT\r\n");
$response = fgets($socket, 515);
logDebug("QUIT response: " . trim($response));

fclose($socket);

if (strpos($response, '250') !== false || strpos($response, '2.0.0') !== false) {
    logDebug("SUCCESS: Email sent successfully");
    
    // Log to database
    try {
        $log_stmt = $conn->prepare("INSERT INTO email_logs (order_id, recipient_email, subject, sent_at, status) VALUES (?, ?, ?, NOW(), 'sent')");
        $log_stmt->bind_param("iss", $order_id, $to_email, $subject);
        $log_stmt->execute();
        $log_stmt->close();
        logDebug("Email logged to database");
    } catch (Exception $e) {
        logDebug("WARNING: Failed to log email to database - " . $e->getMessage());
    }
    
    echo json_encode(['success' => true, 'message' => 'Email sent successfully']);
} else {
    logDebug("ERROR: Email send failed - $response");
    echo json_encode(['success' => false, 'message' => 'Failed to send email']);
}

logDebug("=== EMAIL SEND REQUEST COMPLETED ===\n");
?>