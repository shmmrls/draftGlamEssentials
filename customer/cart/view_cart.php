<?php
require_once __DIR__ . '/../../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to view your cart.';
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get cart items with product details - FIXED QUERY
$cart_items = [];
$stmt = mysqli_prepare($conn, 'SELECT sc.cart_id, sc.product_id, sc.quantity, 
    p.product_name, p.price, p.main_img_name, 
    COALESCE(i.quantity, 0) as stock
    FROM shopping_cart sc
    JOIN products p ON sc.product_id = p.product_id
    LEFT JOIN inventory i ON p.product_id = i.product_id
    WHERE sc.user_id = ?
    ORDER BY sc.added_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { 
    $cart_items = mysqli_fetch_all($res, MYSQLI_ASSOC); 
}
mysqli_stmt_close($stmt);

// Debug: Log what we got
error_log("Cart items count: " . count($cart_items));
foreach ($cart_items as $item) {
    error_log("Cart ID: {$item['cart_id']}, Product: {$item['product_name']}");
}

// Calculate totals
$subtotal = 0;
$total_items = 0;
foreach ($cart_items as &$item) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
    $total_items += $item['quantity'];
    
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

$shipping_fee = 0;
if ($subtotal > 0 && $subtotal < 1000) {
    $shipping_fee = 100;
}

$total = $subtotal + $shipping_fee;

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/cart.css">';
include __DIR__ . '/../../includes/customerHeader.php';
?>

<main class="cart-page">
    <div class="cart-container">
        <div class="page-header">
            <h1 class="page-title">Shopping Cart</h1>
            <div class="breadcrumb">
                <a href="<?php echo $baseUrl; ?>/customer/index.php">Home</a>
                <span class="separator">/</span>
                <span>Cart</span>
            </div>
        </div>

        <!-- Debug Info (remove in production) -->
        <?php if (isset($_GET['debug'])): ?>
        <div style="background: #f0f0f0; padding: 10px; margin: 10px 0; border: 2px solid #333;">
            <strong>Debug Info:</strong><br>
            Total items in array: <?php echo count($cart_items); ?><br>
            <?php foreach ($cart_items as $idx => $item): ?>
                Item <?php echo $idx; ?>: cart_id=<?php echo $item['cart_id']; ?>, 
                product_id=<?php echo $item['product_id']; ?>, 
                name=<?php echo $item['product_name']; ?><br>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Messages -->
        <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span><?php echo htmlspecialchars($_SESSION['success_message']); unset($_SESSION['success_message']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
        <!-- Empty Cart -->
        <div class="empty-cart">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <h2>Your cart is empty</h2>
            <p>Add some products to get started!</p>
            <a href="<?php echo $baseUrl; ?>/customer/product-list.php" class="btn btn-primary">
                Browse Products
            </a>
        </div>
        <?php else: ?>
        <!-- Cart Content -->
        <div class="cart-layout">
            <div class="cart-items-section">
                <div class="cart-header">
                    <h2>Cart Items (<?php echo $total_items; ?>)</h2>
                    <form method="POST" action="<?php echo $baseUrl; ?>/customer/cart/cart_update.php">
    <input type="hidden" name="type" value="clear">
    <input type="hidden" name="redirect" value="<?php echo $baseUrl; ?>/customer/cart/view_cart.php">
    <button type="submit" class="btn-text">Clear Cart</button>
</form>
                </div>

                <div class="cart-items-list">
                    <?php 
                    // Use foreach instead of any other loop to ensure clean iteration
                    foreach ($cart_items as $item): 
                        // Additional safety check
                        if (!isset($item['cart_id']) || !isset($item['product_id'])) {
                            continue;
                        }
                    ?>
                    <div class="cart-item" data-cart-id="<?php echo (int)$item['cart_id']; ?>">
                        <div class="item-image">
                            <a href="<?php echo $baseUrl; ?>/product.php?id=<?php echo (int)$item['product_id']; ?>">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            </a>
                        </div>
                        
                        <div class="item-details">
                            <a href="<?php echo $baseUrl; ?>/product.php?id=<?php echo (int)$item['product_id']; ?>" 
                               class="item-name">
                                <?php echo htmlspecialchars($item['product_name']); ?>
                            </a>
                            <div class="item-price">₱<?php echo number_format((float)$item['price'], 2); ?></div>
                            <?php if ((int)$item['stock'] <= 0): ?>
                            <div class="stock-warning">Out of stock</div>
                            <?php elseif ((int)$item['quantity'] > (int)$item['stock']): ?>
                            <div class="stock-warning">Only <?php echo (int)$item['stock']; ?> available</div>
                            <?php endif; ?>
                        </div>

                        <div class="item-actions">
                            <form method="POST" action="<?php echo $baseUrl; ?>/customer/cart/cart_update.php" 
                                  class="quantity-form">
                                <input type="hidden" name="type" value="update">
                                <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
                                <input type="hidden" name="redirect" value="<?php echo $baseUrl; ?>/customer/cart/view_cart.php">
                                
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" 
                                            onclick="updateQuantity(this, -1, <?php echo (int)$item['stock']; ?>)">−</button>
                                    <input type="number" name="quantity" 
                                           value="<?php echo (int)$item['quantity']; ?>" 
                                           min="1" max="<?php echo (int)$item['stock']; ?>" 
                                           readonly>
                                    <button type="button" class="qty-btn" 
                                            onclick="updateQuantity(this, 1, <?php echo (int)$item['stock']; ?>)">+</button>
                                </div>
                                
                                <button type="submit" class="btn-update">Update</button>
                            </form>

                            <div class="item-subtotal">₱<?php echo number_format((float)$item['subtotal'], 2); ?></div>

                            <form method="POST" action="<?php echo $baseUrl; ?>/customer/cart/cart_update.php">
                                <input type="hidden" name="type" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo (int)$item['cart_id']; ?>">
                                <input type="hidden" name="redirect" value="<?php echo $baseUrl; ?>/customer/cart/view_cart.php">
                                <button type="submit" class="btn-remove">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                                    </svg>
                                    Remove
                                </button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Cart Summary -->
            <div class="cart-summary">
                <h2 class="summary-title">Order Summary</h2>
                
                <div class="summary-row">
                    <span>Subtotal (<?php echo $total_items; ?> items)</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span>Shipping Fee</span>
                    <span><?php echo $shipping_fee > 0 ? '₱' . number_format($shipping_fee, 2) : 'FREE'; ?></span>
                </div>

                <?php if ($subtotal < 1000 && $subtotal > 0): ?>
                <div class="shipping-notice">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                    </svg>
                    <span>Add ₱<?php echo number_format(1000 - $subtotal, 2); ?> more for FREE shipping!</span>
                </div>
                <?php endif; ?>
                
                <div class="summary-divider"></div>
                
                <div class="summary-total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
                
                <a href="<?php echo $baseUrl; ?>/customer/cart/checkout.php" class="btn btn-primary btn-checkout">
                    Proceed to Checkout
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                    </svg>
                </a>
                
                <a href="<?php echo $baseUrl; ?>/customer/product-list.php" class="btn-continue">
                    Continue Shopping
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script src="<?php echo $baseUrl; ?>/customer/js/cart.js"></script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>