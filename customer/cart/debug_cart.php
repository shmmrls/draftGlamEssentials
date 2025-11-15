<?php
// debug_cart.php - Run this to see what's actually in your cart table
require_once __DIR__ . '/../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['user_id'])) {
    die('Please log in first');
}

$user_id = (int)$_SESSION['user_id'];

echo "<h2>Raw Shopping Cart Data for User ID: $user_id</h2>";

// Get ALL cart entries for this user (no joins, just raw data)
$query = "SELECT * FROM shopping_cart WHERE user_id = ? ORDER BY cart_id";
$stmt = mysqli_prepare($conn, $query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$raw_cart = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

echo "<h3>Total cart entries: " . count($raw_cart) . "</h3>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>cart_id</th><th>user_id</th><th>product_id</th><th>quantity</th><th>added_at</th></tr>";

foreach ($raw_cart as $item) {
    echo "<tr>";
    echo "<td>{$item['cart_id']}</td>";
    echo "<td>{$item['user_id']}</td>";
    echo "<td>{$item['product_id']}</td>";
    echo "<td>{$item['quantity']}</td>";
    echo "<td>{$item['added_at']}</td>";
    echo "</tr>";
}
echo "</table>";

// Check for duplicates
echo "<h3>Duplicate Check:</h3>";
$dup_query = "SELECT product_id, COUNT(*) as count, GROUP_CONCAT(cart_id) as cart_ids
              FROM shopping_cart 
              WHERE user_id = ? 
              GROUP BY product_id 
              HAVING count > 1";
$stmt = mysqli_prepare($conn, $dup_query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$duplicates = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (empty($duplicates)) {
    echo "<p style='color: green;'>No duplicates found ✓</p>";
} else {
    echo "<p style='color: red;'>⚠️ DUPLICATES FOUND:</p>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Product ID</th><th>Duplicate Count</th><th>Cart IDs</th></tr>";
    foreach ($duplicates as $dup) {
        echo "<tr>";
        echo "<td>{$dup['product_id']}</td>";
        echo "<td>{$dup['count']}</td>";
        echo "<td>{$dup['cart_ids']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Now check what the view_cart.php query returns
echo "<h3>What view_cart.php sees:</h3>";
$view_query = "SELECT sc.cart_id, sc.product_id, sc.quantity, 
    p.product_name, p.price, p.main_img_name, 
    i.quantity as stock
    FROM shopping_cart sc
    JOIN products p ON sc.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE sc.user_id = ?
    ORDER BY sc.added_at DESC";
$stmt = mysqli_prepare($conn, $view_query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$view_data = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

echo "<p>Query returns " . count($view_data) . " rows</p>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>cart_id</th><th>product_id</th><th>product_name</th><th>quantity</th><th>stock</th></tr>";
foreach ($view_data as $item) {
    echo "<tr>";
    echo "<td>{$item['cart_id']}</td>";
    echo "<td>{$item['product_id']}</td>";
    echo "<td>{$item['product_name']}</td>";
    echo "<td>{$item['quantity']}</td>";
    echo "<td>{$item['stock']}</td>";
    echo "</tr>";
}
echo "</table>";
?>

<h3>Actions:</h3>
<a href="clean_duplicates.php" style="background: red; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 10px 0;">
    🧹 Clean All Duplicates
</a>
<br>
<a href="view_cart.php" style="background: blue; color: white; padding: 10px; text-decoration: none; display: inline-block;">
    ← Back to Cart
</a>