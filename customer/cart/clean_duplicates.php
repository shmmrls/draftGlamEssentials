<?php
// clean_duplicates.php - This will fix your cart
require_once __DIR__ . '/../../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['user_id'])) {
    die('Please log in first');
}

$user_id = (int)$_SESSION['user_id'];

echo "<h2>Cleaning Cart Duplicates for User ID: $user_id</h2>";

// Step 1: Find all duplicates
$dup_query = "SELECT product_id, COUNT(*) as count, GROUP_CONCAT(cart_id ORDER BY added_at DESC) as cart_ids
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
    echo "<p style='color: green;'>✓ No duplicates found! Cart is clean.</p>";
} else {
    echo "<p style='color: orange;'>Found " . count($duplicates) . " products with duplicates</p>";
    
    foreach ($duplicates as $dup) {
        $product_id = $dup['product_id'];
        $cart_ids = explode(',', $dup['cart_ids']);
        $keep_id = $cart_ids[0]; // Keep the newest one (first in DESC order)
        $delete_ids = array_slice($cart_ids, 1); // Delete the rest
        
        echo "<p>Product ID $product_id has {$dup['count']} entries</p>";
        echo "<ul>";
        echo "<li>Keeping cart_id: $keep_id</li>";
        echo "<li>Deleting cart_ids: " . implode(', ', $delete_ids) . "</li>";
        echo "</ul>";
        
        // Delete duplicates
        foreach ($delete_ids as $delete_id) {
            $del_stmt = mysqli_prepare($conn, "DELETE FROM shopping_cart WHERE cart_id = ? AND user_id = ?");
            mysqli_stmt_bind_param($del_stmt, 'ii', $delete_id, $user_id);
            mysqli_stmt_execute($del_stmt);
            mysqli_stmt_close($del_stmt);
        }
    }
    
    echo "<p style='color: green; font-weight: bold;'>✓ Cleanup complete!</p>";
}

// Step 2: Add unique constraint to prevent future duplicates
echo "<h3>Adding Unique Constraint...</h3>";
$check_constraint = "SHOW INDEXES FROM shopping_cart WHERE Key_name = 'unique_user_product'";
$result = mysqli_query($conn, $check_constraint);

if (mysqli_num_rows($result) == 0) {
    $add_constraint = "ALTER TABLE shopping_cart ADD UNIQUE KEY unique_user_product (user_id, product_id)";
    if (mysqli_query($conn, $add_constraint)) {
        echo "<p style='color: green;'>✓ Unique constraint added successfully!</p>";
    } else {
        echo "<p style='color: red;'>⚠️ Could not add constraint: " . mysqli_error($conn) . "</p>";
    }
} else {
    echo "<p style='color: green;'>✓ Unique constraint already exists</p>";
}

// Step 3: Show current cart
echo "<h3>Current Cart Contents:</h3>";
$view_query = "SELECT sc.cart_id, sc.product_id, p.product_name, sc.quantity
               FROM shopping_cart sc
               JOIN products p ON sc.product_id = p.product_id
               WHERE sc.user_id = ?
               ORDER BY sc.added_at DESC";
$stmt = mysqli_prepare($conn, $view_query);
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$current_cart = mysqli_fetch_all($result, MYSQLI_ASSOC);
mysqli_stmt_close($stmt);

if (empty($current_cart)) {
    echo "<p>Cart is empty</p>";
} else {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Cart ID</th><th>Product ID</th><th>Product Name</th><th>Quantity</th></tr>";
    foreach ($current_cart as $item) {
        echo "<tr>";
        echo "<td>{$item['cart_id']}</td>";
        echo "<td>{$item['product_id']}</td>";
        echo "<td>{$item['product_name']}</td>";
        echo "<td>{$item['quantity']}</td>";
        echo "</tr>";
    }
    echo "</table>";
}

mysqli_close($conn);
?>

<h3>Next Steps:</h3>
<ol>
    <li>✓ Duplicates removed</li>
    <li>✓ Unique constraint added</li>
    <li>Now test adding products again</li>
</ol>

<a href="view_cart.php" style="background: green; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 10px 0;">
    ← Back to Cart
</a>