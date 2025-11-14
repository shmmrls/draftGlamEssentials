<?php
/**
 * MP5: Transaction using prepared statements (10pts)
 * Handles all shopping cart operations: add, update, remove
 */

require_once __DIR__ . '/../../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to add items to your cart.';
    header('Location: ../user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../index.php');
    exit;
}

$type = $_POST['type'] ?? '';
$redirect = $_POST['redirect'] ?? 'view_cart.php';

// MP5: Add to cart
if ($type === 'add') {
    $item_id = isset($_POST['item_id']) ? (int)$_POST['item_id'] : 0;
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    if ($item_id <= 0) {
        $_SESSION['error_message'] = 'Invalid product selected.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Verify product exists and is available
    $stmt = mysqli_prepare($conn, 'SELECT product_id, product_name, is_available FROM products WHERE product_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $product = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    
    if (!$product || !$product['is_available']) {
        $_SESSION['error_message'] = 'This product is not available.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Check inventory
    $stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE product_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $inventory = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    
    if (!$inventory || $inventory['quantity'] < $quantity) {
        $_SESSION['error_message'] = 'Insufficient stock available.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Check if item already in cart
    $stmt = mysqli_prepare($conn, 'SELECT cart_id, quantity FROM shopping_cart WHERE user_id = ? AND product_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $user_id, $item_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $existing = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    
    if ($existing) {
        // Update existing cart item
        $new_quantity = $existing['quantity'] + $quantity;
        
        // Check if new quantity exceeds stock
        if ($new_quantity > $inventory['quantity']) {
            $_SESSION['error_message'] = 'Cannot add more items. Stock limit reached.';
            header('Location: ' . $redirect);
            exit;
        }
        
        $stmt = mysqli_prepare($conn, 'UPDATE shopping_cart SET quantity = ? WHERE cart_id = ?');
        mysqli_stmt_bind_param($stmt, 'ii', $new_quantity, $existing['cart_id']);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            $_SESSION['success_message'] = 'Cart updated successfully!';
        } else {
            $_SESSION['error_message'] = 'Failed to update cart. Please try again.';
        }
    } else {
        // Add new item to cart
        $stmt = mysqli_prepare($conn, 'INSERT INTO shopping_cart (user_id, product_id, quantity) VALUES (?, ?, ?)');
        mysqli_stmt_bind_param($stmt, 'iii', $user_id, $item_id, $quantity);
        $success = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        
        if ($success) {
            $_SESSION['success_message'] = 'Product added to cart!';
        } else {
            $_SESSION['error_message'] = 'Failed to add product to cart. Please try again.';
        }
    }
    
    header('Location: ' . $redirect);
    exit;
}

// MP5: Update cart quantity
if ($type === 'update') {
    $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
    $quantity = isset($_POST['quantity']) ? max(1, (int)$_POST['quantity']) : 1;
    
    if ($cart_id <= 0) {
        $_SESSION['error_message'] = 'Invalid cart item.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Verify cart item belongs to user
    $stmt = mysqli_prepare($conn, 'SELECT sc.product_id FROM shopping_cart sc WHERE sc.cart_id = ? AND sc.user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $cart_item = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    
    if (!$cart_item) {
        $_SESSION['error_message'] = 'Cart item not found.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Check inventory
    $stmt = mysqli_prepare($conn, 'SELECT quantity FROM inventory WHERE product_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $cart_item['product_id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $inventory = $result ? mysqli_fetch_assoc($result) : null;
    mysqli_stmt_close($stmt);
    
    if (!$inventory || $inventory['quantity'] < $quantity) {
        $_SESSION['error_message'] = 'Insufficient stock available.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Update quantity
    $stmt = mysqli_prepare($conn, 'UPDATE shopping_cart SET quantity = ? WHERE cart_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $quantity, $cart_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        $_SESSION['success_message'] = 'Cart updated successfully!';
    } else {
        $_SESSION['error_message'] = 'Failed to update cart. Please try again.';
    }
    
    header('Location: ' . $redirect);
    exit;
}

// MP5: Remove from cart
if ($type === 'remove') {
    $cart_id = isset($_POST['cart_id']) ? (int)$_POST['cart_id'] : 0;
    
    if ($cart_id <= 0) {
        $_SESSION['error_message'] = 'Invalid cart item.';
        header('Location: ' . $redirect);
        exit;
    }
    
    // Verify cart item belongs to user and delete
    $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE cart_id = ? AND user_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $cart_id, $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        $_SESSION['success_message'] = 'Item removed from cart.';
    } else {
        $_SESSION['error_message'] = 'Failed to remove item. Please try again.';
    }
    
    header('Location: ' . $redirect);
    exit;
}

// MP5: Clear entire cart
if ($type === 'clear') {
    $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE user_id = ?');
    mysqli_stmt_bind_param($stmt, 'i', $user_id);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        $_SESSION['success_message'] = 'Cart cleared successfully.';
    } else {
        $_SESSION['error_message'] = 'Failed to clear cart. Please try again.';
    }
    
    header('Location: ' . $redirect);
    exit;
}

// Invalid type
$_SESSION['error_message'] = 'Invalid operation.';
header('Location: ' . $redirect);
exit;