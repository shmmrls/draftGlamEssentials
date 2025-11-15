<?php
require_once __DIR__ . '/../../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Verify database connection
if (!isset($conn) || !$conn) {
    die('Database connection failed');
}

// DEBUG: Show all received data
error_log("=== REQUEST METHOD: " . $_SERVER['REQUEST_METHOD'] . " ===");
error_log("POST data: " . print_r($_POST, true));
error_log("place_order isset: " . (isset($_POST['place_order']) ? 'YES' : 'NO'));

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to checkout.';
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get customer details
$customer = null;
$stmt = mysqli_prepare($conn, 'SELECT customer_id, fullname, address, contact_no, zipcode, town FROM customers WHERE user_id = ? LIMIT 1');
if (!$stmt) {
    die('Prepare failed: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $customer = mysqli_fetch_assoc($res); }
mysqli_stmt_close($stmt);

if (!$customer) {
    $_SESSION['error_message'] = 'Please complete your profile before checkout.';
    header('Location: ' . $baseUrl . '/user/edit_profile.php');
    exit;
}

// Get cart items - FIXED QUERY (same as view_cart.php)
$cart_items = [];
$stmt = mysqli_prepare($conn, 'SELECT sc.cart_id, sc.product_id, sc.quantity, 
    p.product_name, p.price, p.main_img_name, 
    COALESCE(i.quantity, 0) as stock
    FROM shopping_cart sc
    JOIN products p ON sc.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE sc.user_id = ?
    ORDER BY sc.added_at DESC');
if (!$stmt) {
    die('Prepare failed: ' . mysqli_error($conn));
}
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $cart_items = mysqli_fetch_all($res, MYSQLI_ASSOC); }
mysqli_stmt_close($stmt);

// Debug: Log what we got
error_log("Cart items count: " . count($cart_items));
foreach ($cart_items as $item) {
    error_log("Cart ID: {$item['cart_id']}, Product: {$item['product_name']}");
}

if (empty($cart_items)) {
    $_SESSION['error_message'] = 'Your cart is empty.';
    header('Location: ' . $baseUrl . '/customer/cart/view_cart.php');
    exit;
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
$has_stock_issues = false;

foreach ($cart_items as &$item) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
    $total_items += $item['quantity'];
    
    if ($item['stock'] <= 0 || $item['quantity'] > $item['stock']) {
        $has_stock_issues = true;
    }
    
    // Get image path
    $imgName = $item['main_img_name'] ?? '';
    $item['image'] = $baseUrl . '/assets/default.png';
    if (!empty($imgName)) {
        $productImagesDir = __DIR__ . '/../../item/products/';
        foreach (['.jpg', '.png', '.webp'] as $ext) {
            $fullPath = $productImagesDir . $imgName . $ext;
            if (file_exists($fullPath)) {
                $item['image'] = $baseUrl . '/item/products/' . $imgName . $ext;
                break;
            }
        }
    }
}
unset($item); // Break reference

$shipping_fee = ($subtotal > 0 && $subtotal < 1000) ? 100 : 0;
$total = $subtotal + $shipping_fee;

// ========== HANDLE FORM SUBMISSION ==========
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST REQUEST DETECTED");
    error_log("POST keys: " . implode(', ', array_keys($_POST)));
    
    if (isset($_POST['place_order'])) {
        error_log("PLACE ORDER BUTTON DETECTED - Processing order...");
        
        // Re-check stock
        $has_stock_issues = false;
        foreach ($cart_items as $item) {
            if ($item['stock'] <= 0 || $item['quantity'] > $item['stock']) {
                $has_stock_issues = true;
                break;
            }
        }
        
        if ($has_stock_issues) {
            $_SESSION['error_message'] = 'Some items in your cart are out of stock or exceed available quantity.';
            header('Location: ' . $baseUrl . '/customer/cart/checkout.php');
            exit;
        }
        
        // Get and validate form data
        $shipping_name = trim($_POST['shipping_name'] ?? '');
        $shipping_address = trim($_POST['shipping_address'] ?? '');
        $shipping_contact = trim($_POST['shipping_contact'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');
        
        // Get city and zip from customer profile and combine with address
        $full_address = $shipping_address;
        if (!empty($customer['town'])) {
            $full_address .= ', ' . $customer['town'];
        }
        if (!empty($customer['zipcode'])) {
            $full_address .= ' ' . $customer['zipcode'];
        }
        
        error_log("Form data - Name: '$shipping_name', Address: '$full_address', Contact: '$shipping_contact', Payment: '$payment_method'");
        
        $errors = [];
        if (empty($shipping_name)) $errors[] = 'Shipping name is required.';
        if (empty($shipping_address)) $errors[] = 'Shipping address is required.';
        if (empty($shipping_contact)) $errors[] = 'Contact number is required.';
        if (!in_array($payment_method, ['Cash on Delivery', 'GCash', 'Credit Card'])) {
            $errors[] = 'Please select a valid payment method.';
        }
        
        if (!empty($errors)) {
            $_SESSION['error_message'] = implode(' ', $errors);
            error_log("Validation errors: " . implode(', ', $errors));
            header('Location: ' . $baseUrl . '/customer/cart/checkout.php');
            exit;
        }
        
        // Start transaction
        mysqli_begin_transaction($conn);
        
        try {
            // Generate transaction ID
            $transaction_id = 'TXN' . date('Ymd') . str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            error_log("Creating order with transaction ID: $transaction_id");
            
            // Insert order - using $full_address which includes city and zip
            $stmt = mysqli_prepare($conn, 'INSERT INTO orders 
                (transaction_id, customer_id, shipping_name, shipping_address, shipping_contact, payment_method, total_amount) 
                VALUES (?, ?, ?, ?, ?, ?, ?)');
            
            if (!$stmt) {
                throw new Exception('Failed to prepare order statement: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 'sissssd', 
                $transaction_id, 
                $customer['customer_id'], 
                $shipping_name, 
                $full_address,  // Using combined address here
                $shipping_contact, 
                $payment_method, 
                $total
            );
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to insert order: ' . mysqli_stmt_error($stmt));
            }
            
            $order_id = mysqli_insert_id($conn);
            mysqli_stmt_close($stmt);
            
            if (!$order_id) {
                throw new Exception('Failed to get order ID');
            }
            
            error_log("Order created with ID: $order_id");
            
            // Insert order items and update inventory - FIXED LOOP
            foreach ($cart_items as $item) {
                // Additional safety check
                if (!isset($item['product_id']) || !isset($item['quantity'])) {
                    error_log("WARNING: Skipping invalid cart item");
                    continue;
                }
                
                // Insert order item
                $stmt = mysqli_prepare($conn, 'INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) 
                    VALUES (?, ?, ?, ?, ?)');
                
                if (!$stmt) {
                    throw new Exception('Failed to prepare order item statement: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'iiidd', 
                    $order_id, 
                    $item['product_id'], 
                    $item['quantity'], 
                    $item['price'], 
                    $item['subtotal']
                );
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to insert order item: ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
                
                // Update inventory
                $stmt = mysqli_prepare($conn, 'UPDATE inventory SET quantity = quantity - ? WHERE product_id = ?');
                if (!$stmt) {
                    throw new Exception('Failed to prepare inventory update: ' . mysqli_error($conn));
                }
                
                mysqli_stmt_bind_param($stmt, 'ii', $item['quantity'], $item['product_id']);
                
                if (!mysqli_stmt_execute($stmt)) {
                    throw new Exception('Failed to update inventory: ' . mysqli_stmt_error($stmt));
                }
                mysqli_stmt_close($stmt);
                
                error_log("Added item to order: Product {$item['product_id']}, Qty {$item['quantity']}");
            }
            
            // Clear cart
            $stmt = mysqli_prepare($conn, 'DELETE FROM shopping_cart WHERE user_id = ?');
            if (!$stmt) {
                throw new Exception('Failed to prepare cart clear: ' . mysqli_error($conn));
            }
            
            mysqli_stmt_bind_param($stmt, 'i', $user_id);
            
            if (!mysqli_stmt_execute($stmt)) {
                throw new Exception('Failed to clear cart: ' . mysqli_stmt_error($stmt));
            }
            mysqli_stmt_close($stmt);
            
            // Commit transaction
            mysqli_commit($conn);
            
            error_log("Order completed successfully!");
            
            // Set success message and redirect
            $_SESSION['success_message'] = 'Order placed successfully! Your order number is ' . $transaction_id;
            
            // Use absolute redirect
            $redirect_url = $baseUrl . '/customer/cart/order_confirmation.php?order_id=' . $order_id;
            error_log("Redirecting to: $redirect_url");
            
            header('Location: ' . $redirect_url);
            exit;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            error_log("Order failed: " . $e->getMessage());
            $_SESSION['error_message'] = 'Failed to place order: ' . $e->getMessage();
            header('Location: ' . $baseUrl . '/customer/cart/checkout.php');
            exit;
        }
    } else {
        error_log("POST received but place_order not set!");
    }
}

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/cart.css">';
include __DIR__ . '/../../includes/customerHeader.php';
?>

<main class="checkout-page">
    <div class="checkout-container">
        <div class="page-header">
            <h1 class="page-title">Checkout</h1>
            <div class="breadcrumb">
                <a href="<?php echo $baseUrl; ?>/customer/index.php">Home</a>
                <span class="separator">/</span>
                <a href="<?php echo $baseUrl; ?>/customer/cart/view_cart.php">Cart</a>
                <span class="separator">/</span>
                <span>Checkout</span>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
        </div>
        <?php endif; ?>

        <?php if ($has_stock_issues): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span>Some items are out of stock or exceed available quantity. Please update your cart.</span>
        </div>
        <?php endif; ?>

        <div class="checkout-layout">
            <!-- Checkout Form -->
            <div class="checkout-form-section">
                <form method="POST" id="checkoutForm">
                    <div class="form-section">
                        <h2 class="section-title">Shipping Information</h2>
                        
                        <div class="form-group">
                            <label for="shipping_name">Full Name <span class="required">*</span></label>
                            <input type="text" id="shipping_name" name="shipping_name" 
                                   value="<?php echo htmlspecialchars($customer['fullname'] ?? ''); ?>" 
                                   required>
                        </div>

                        <div class="form-group">
                            <label for="shipping_address">Complete Address <span class="required">*</span></label>
                            <textarea id="shipping_address" name="shipping_address" rows="3" required><?php 
                                echo htmlspecialchars($customer['address'] ?? ''); 
                            ?></textarea>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_contact">Contact Number <span class="required">*</span></label>
                                <input type="tel" id="shipping_contact" name="shipping_contact" 
                                       value="<?php echo htmlspecialchars($customer['contact_no'] ?? ''); ?>" 
                                       placeholder="09XXXXXXXXX" required>
                            </div>

                            <div class="form-group">
                                <label for="zipcode">Zip Code</label>
                                <input type="text" id="zipcode" name="zipcode" 
                                       value="<?php echo htmlspecialchars($customer['zipcode'] ?? ''); ?>" 
                                       placeholder="1234">
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h2 class="section-title">Payment Method</h2>
                        
                        <div class="payment-methods">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Cash on Delivery" checked required>
                                <div class="payment-card">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    <div>
                                        <div class="payment-name">Cash on Delivery</div>
                                        <div class="payment-desc">Pay when you receive your order</div>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="GCash" required>
                                <div class="payment-card">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    <div>
                                        <div class="payment-name">GCash</div>
                                        <div class="payment-desc">Pay via GCash mobile wallet</div>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Credit Card" required>
                                <div class="payment-card">
                                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                    </svg>
                                    <div>
                                        <div class="payment-name">Credit/Debit Card</div>
                                        <div class="payment-desc">Visa, Mastercard, etc.</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <button type="submit" name="place_order" value="1" class="btn btn-primary btn-place-order" 
                            <?php echo $has_stock_issues ? 'disabled' : ''; ?>>
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Place Order
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary-section">
                <h2 class="section-title">Order Summary</h2>
                
                <div class="summary-items">
                    <?php 
                    // Use foreach to ensure clean iteration (same as view_cart.php)
                    foreach ($cart_items as $item): 
                        // Additional safety check
                        if (!isset($item['product_id']) || !isset($item['product_name'])) {
                            continue;
                        }
                    ?>
                    <div class="summary-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="summary-item-info">
                            <div class="summary-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="summary-item-qty">Qty: <?php echo (int)$item['quantity']; ?></div>
                        </div>
                        <div class="summary-item-price">₱<?php echo number_format((float)$item['subtotal'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-row">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>

                <div class="summary-row">
                    <span>Shipping</span>
                    <span><?php echo $shipping_fee > 0 ? '₱' . number_format($shipping_fee, 2) : 'FREE'; ?></span>
                </div>

                <div class="summary-divider"></div>

                <div class="summary-total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
console.log('Page loaded, checking for checkout.js...');
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>