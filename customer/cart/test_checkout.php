<?php
// test_checkout_final.php - Final checkout test
require_once __DIR__ . '/../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['user_id'])) {
    die('Please log in first');
}

$user_id = (int)$_SESSION['user_id'];

echo "<h2>✅ Final Checkout Test for User ID: $user_id</h2>";

// Quick checks
$checks = [];

// Customer check
$stmt = mysqli_prepare($conn, 'SELECT customer_id FROM customers WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$customer = mysqli_stmt_get_result($stmt)->fetch_assoc();
mysqli_stmt_close($stmt);
$checks['customer'] = $customer ? true : false;

// Cart check
$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as count FROM shopping_cart WHERE user_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$cart_count = mysqli_stmt_get_result($stmt)->fetch_assoc()['count'];
mysqli_stmt_close($stmt);
$checks['cart'] = $cart_count > 0;

// Stock check
$stmt = mysqli_prepare($conn, 'SELECT COUNT(*) as count FROM shopping_cart sc
    JOIN products p ON sc.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE sc.user_id = ? AND (i.quantity <= 0 OR sc.quantity > i.quantity)');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$stock_issues = mysqli_stmt_get_result($stmt)->fetch_assoc()['count'];
mysqli_stmt_close($stmt);
$checks['stock'] = $stock_issues == 0;

// Database tables check
$tables = ['orders', 'order_items', 'inventory'];
$checks['tables'] = true;
foreach ($tables as $table) {
    $result = mysqli_query($conn, "SHOW TABLES LIKE '$table'");
    if (mysqli_num_rows($result) == 0) {
        $checks['tables'] = false;
        break;
    }
}

echo "<div style='font-family: Arial, sans-serif;'>";
echo "<h3>System Status:</h3>";
echo "<p>Customer Profile: " . ($checks['customer'] ? '✅ OK' : '❌ Missing') . "</p>";
echo "<p>Cart Items: " . ($checks['cart'] ? "✅ $cart_count items" : '❌ Empty') . "</p>";
echo "<p>Stock Availability: " . ($checks['stock'] ? '✅ All items in stock' : '❌ Stock issues') . "</p>";
echo "<p>Database Tables: " . ($checks['tables'] ? '✅ All required tables exist' : '❌ Missing tables') . "</p>";

$all_good = $checks['customer'] && $checks['cart'] && $checks['stock'] && $checks['tables'];

if ($all_good) {
    echo "<h2 style='color: green;'>🎉 CHECKOUT IS READY!</h2>";
    echo "<p>All systems are go. You can place your order now.</p>";
    echo "<a href='checkout.php' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px; border-radius: 5px; display: inline-block;'>🛒 Proceed to Checkout</a>";
} else {
    echo "<h2 style='color: red;'>⚠️ CHECKOUT NOT READY</h2>";
    echo "<p>Please fix the issues above before proceeding.</p>";
    
    if (!$checks['customer']) {
        echo "<p><a href='../../customer/profile.php'>Create Customer Profile</a></p>";
    }
    if (!$checks['cart']) {
        echo "<p><a href='../../products.php'>Add Items to Cart</a></p>";
    }
    if (!$checks['stock']) {
        echo "<p><a href='view_cart.php'>Update Cart Quantities</a></p>";
    }
}

echo "</div>";

?><a href="checkout.php" style="background: green; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 5px;">Go to Checkout</a>
<a href="debug_cart.php" style="background: orange; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 5px;">Debug Cart</a>