<?php
require_once __DIR__ . '/../../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to manage your cart.';
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'] ?? '';
    $redirect = $_POST['redirect'] ?? $baseUrl . '/customer/cart/view_cart.php';
    
    switch ($type) {
        case 'add':
            // Add item to cart
            $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
            $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
            
            if ($item_id <= 0) {
                $_SESSION['error_message'] = 'Invalid product.';
                header('Location: ' . $redirect);
                exit;
            }
            
            // Check if product exists and has stock
            $stmt = mysqli_prepare($conn, 'SELECT p.product_id, p.product_name, i.quantity FROM products p 
                LEFT JOIN inventory i ON p.product_id = i.product_id 
                WHERE p.product_id = ? AND p.is_available = 1');
            mysqli_stmt_bind_param($stmt, 'i', $item_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $product = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            
            if (!$product) {
                $_SESSION['error_message'] = 'Product not available.';
                header('Location: ' . $redirect);
                exit;
            }
            
            $available_stock = (int)($product['quantity'] ?? 0);
            if ($available_stock <= 0) {
                $_SESSION['error_message'] = 'Product is out of stock.';
                header('Location: ' . $redirect);
                exit;
            }
            
            // FIXED: Check if item already in cart - use LIMIT 1 to ensure single result
            $stmt = mysqli_prepare($conn, 'SELECT cart_id, quantity FROM shopping_cart WHERE user_id = ? AND product_id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'ii', $user_id, $item_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $existing = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            
            if ($existing) {
                // Update quantity
                $new_quantity = min($existing['quantity'] + $quantity, $available_stock);
                $stmt = mysqli_prepare($conn, 'UPDATE shopping_cart SET quantity = ?, added_at = NOW() WHERE cart_id = ?');
                mysqli_stmt_bind_param($stmt, 'ii', $new_quantity, $existing['cart_id']);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success_message'] = 'Cart updated successfully!';
            } else {
                // Insert new cart item
                $quantity = min($quantity, $available_stock);
                $stmt = mysqli_prepare($conn, 'INSERT INTO shopping_cart (user_id, product_id, quantity, added_at) VALUES (?, ?, ?, NOW())');
                mysqli_stmt_bind_param($stmt, 'iii', $user_id, $item_id, $quantity);
                mysqli_stmt_execute($stmt);
                mysqli_stmt_close($stmt);
                $_SESSION['success_message'] = 'Product added to cart!';
            }
            break;
            
        case 'update':
            // Update cart item quantity
            $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
            $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
            
            if ($cart_id <= 0) {
                $_SESSION['error_message'] = 'Invalid cart item.';
                header('Location: ' . $redirect);
                exit;
            }
            
            // Verify ownership and get product stock
            $stmt = mysqli_prepare($conn, 'SELECT sc.cart_id, i.quantity as stock 
                FROM shopping_cart sc 
                JOIN products p ON sc.product_id = p.product_id
                LEFT JOIN inventory i ON p.product_id = i.product_id
                WHERE sc.cart_id = ? AND sc.user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            $cart_item = $res ? mysqli_fetch_assoc($res) : null;
            mysqli_stmt_close($stmt);
            
            if (!$cart_item) {
                $_SESSION['error_message'] = 'Cart item not found.';
                header('Location: ' . $redirect);
                exit;
            }
            
            $available_stock = (int)($cart_item['stock'] ?? 0);
            $quantity = min($quantity, $available_stock);
            
            $stmt = mysqli_prepare($conn, 'UPDATE shopping_cart SET quantity = ? WHERE cart_id = ? AND user_id = ?');
            mysqli_stmt_bind_param($stmt, 'iii', $quantity, $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $_SESSION['success_message'] = 'Cart updated successfully!';
            break;
            
        case 'remove':
            // Remove item from cart
            $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
            
            if ($cart_id <= 0) {
                $_SESSION['error_message'] = 'Invalid cart item.';
                header('Location: ' . $redirect);
                exit;
            }
            
            $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE cart_id = ? AND user_id = ? LIMIT 1');
            mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $_SESSION['success_message'] = 'Item removed from cart.';
            break;
            
        case 'clear':
            // Clear entire cart
            $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE user_id = ?');
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            
            $_SESSION['success_message'] = 'Cart cleared successfully.';
            break;
            
        default:
            $_SESSION['error_message'] = 'Invalid action.';
            break;
    }
    
    header('Location: ' . $redirect);
    exit;
}

// If not POST, redirect to cart
header('Location: ' . $baseUrl . '/customer/cart/view_cart.php');
exit;
?>