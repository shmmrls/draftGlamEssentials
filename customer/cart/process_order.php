<?php
/**
 * MP5: Complete transaction using prepared statements (10pts)
 * FR4: Checkout and Order Processing
 * FR4.4: Use prepared statements for all database operations
 * FR4.5: Generate unique transaction ID
 * FR4.6: Store order details
 * FR5.1: Decrease product stock automatically
 */

require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to place an order.';
    header('Location: ../user/login.php');
    exit;
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $baseUrl . '/cart/checkout.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get customer ID
$stmt = mysqli_prepare($conn, 'SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$customer) {
    $_SESSION['error_message'] = 'Customer profile not found.';
    header('Location: ' . $baseUrl . '/cart/checkout.php');
    exit;
}

$customer_id = (int)$customer['customer_id'];

// Collect and validate form data
$shipping_name = trim($_POST['shipping_name'] ?? '');
$shipping_contact = trim($_POST['shipping_contact'] ?? '');
$shipping_address = trim($_POST['shipping_address'] ?? '');
$shipping_town = trim($_POST['shipping_town'] ?? '');
$shipping_zipcode = trim($_POST['shipping_zipcode'] ?? '');
$payment_method = $_POST['payment_method'] ?? '';
$order_notes = trim($_POST['order_notes'] ?? '');

// Server-side validation
$errors = [];

if (empty($shipping_name) || strlen($shipping_name) < 3) {
    $errors[] = 'Please provide a valid full name.';
}

if (empty($shipping_contact) || !preg_match('/^(09|\+639)\d{9}$/', str_replace(' ', '', $shipping_contact))) {
    $errors[] = 'Please provide a valid Philippine mobile number.';
}

if (empty($shipping_address) || strlen($shipping_address) < 10) {
    $errors[] = 'Please provide a complete address.';
}

if (empty($shipping_town)) {
    $errors[] = 'Please provide a city/municipality.';
}

if (empty($shipping_zipcode) || !preg_match('/^\d{4}$/', $shipping_zipcode)) {
    $errors[] = 'Please provide a valid 4-digit ZIP code.';
}

if (!in_array($payment_method, ['Cash on Delivery', 'GCash', 'Credit Card'])) {
    $errors[] = 'Please select a valid payment method.';
}

if (!empty($errors)) {
    $_SESSION['error_message'] = implode(' ', $errors);
    header('Location: ' . $baseUrl . '/cart/checkout.php');
    exit;
}

// Combine full shipping address
$full_shipping_address = $shipping_address . ', ' . $shipping_town . ', ' . $shipping_zipcode;

// Fetch cart items with stock check
$stmt = mysqli_prepare($conn, 
    'SELECT sc.cart_id, sc.product_id, sc.quantity,
            p.product_name, p.price, p.is_available,
            i.quantity as stock_quantity
     FROM shopping_cart sc
     INNER JOIN products p ON sc.product_id = p.product_id
     LEFT JOIN inventory i ON p.product_id = i.product_id
     WHERE sc.user_id = ?');

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);

if (empty($cart_items)) {
    $_SESSION['error_message'] = 'Your cart is empty.';
    header('Location: ' . $baseUrl . '/cart/view_cart.php');
    exit;
}

// Validate stock availability
$stock_errors = [];
foreach ($cart_items as $item) {
    if (!$item['is_available']) {
        $stock_errors[] = $item['product_name'] . ' is no longer available';
    } elseif ($item['stock_quantity'] < $item['quantity']) {
        $stock_errors[] = $item['product_name'] . ' has insufficient stock';
    }
}

if (!empty($stock_errors)) {
    $_SESSION['error_message'] = implode('. ', $stock_errors) . '. Please update your cart.';
    header('Location: ' . $baseUrl . '/cart/view_cart.php');
    exit;
}

// Calculate totals
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

$shipping_fee = 150.00;
$total_amount = $subtotal + $shipping_fee;

// FR4.5: Generate unique transaction ID
$transaction_id = 'TXN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -8));

// Begin transaction
mysqli_begin_transaction($conn);

try {
    // FR4.6: Insert order record using prepared statements
    $stmt = mysqli_prepare($conn, 
        'INSERT INTO orders (transaction_id, customer_id, shipping_name, shipping_address, shipping_contact, 
                            payment_method, payment_status, order_status, total_amount, order_date)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    
    $payment_status = 'Pending';
    $order_status = 'Pending';
    
    mysqli_stmt_bind_param($stmt, 'sisssssd', 
        $transaction_id, $customer_id, $shipping_name, $full_shipping_address, 
        $shipping_contact, $payment_method, $payment_status, $order_status, $total_amount);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to create order.');
    }
    
    $order_id = mysqli_insert_id($conn);
    mysqli_stmt_close($stmt);
    
    // Insert order items and update inventory
    $stmt_order_item = mysqli_prepare($conn, 
        'INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
         VALUES (?, ?, ?, ?, ?)');
    
    $stmt_update_inventory = mysqli_prepare($conn, 
        'UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?');
    
    foreach ($cart_items as $item) {
        $item_subtotal = $item['price'] * $item['quantity'];
        
        // Insert order item
        mysqli_stmt_bind_param($stmt_order_item, 'iiidd', 
            $order_id, $item['product_id'], $item['quantity'], $item['price'], $item_subtotal);
        
        if (!mysqli_stmt_execute($stmt_order_item)) {
            throw new Exception('Failed to add order items.');
        }
        
        // FR5.1: Decrease inventory stock
        mysqli_stmt_bind_param($stmt_update_inventory, 'ii', 
            $item['quantity'], $item['product_id']);
        
        if (!mysqli_stmt_execute($stmt_update_inventory)) {
            throw new Exception('Failed to update inventory.');
        }
    }
    
    mysqli_stmt_close($stmt_order_item);
    mysqli_stmt_close($stmt_update_inventory);
    
    // Clear shopping cart
    $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    
    if (!mysqli_stmt_execute($stmt)) {
        throw new Exception('Failed to clear cart.');
    }
    
    mysqli_stmt_close($stmt);
    
    // Commit transaction
    mysqli_commit($conn);
    
    // Set success message
    $_SESSION['success_message'] = 'Order placed successfully! Your transaction ID is: ' . $transaction_id;
    $_SESSION['last_order_id'] = $order_id;
    $_SESSION['last_transaction_id'] = $transaction_id;
    
    // Redirect to order confirmation page
    header('Location: order_confirmation.php?order_id=' . $order_id);
    exit;
    
} catch (Exception $e) {
    // Rollback transaction on error
    mysqli_rollback($conn);
    
    $_SESSION['error_message'] = 'Failed to process order: ' . $e->getMessage();
    header('Location: ' . $baseUrl . '/cart/checkout.php');
    exit;
}