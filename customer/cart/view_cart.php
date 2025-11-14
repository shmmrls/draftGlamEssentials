<?php
/**
 * MP5: Shopping Cart View
 * FR3: Shopping Cart (Transaction Function)
 * FR3.2: Display all items in cart with details
 * FR3.4: Automatically recalculate total price
 */

require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to view your cart.';
    header('Location: ../user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Fetch cart items with product details using prepared statements
$stmt = mysqli_prepare($conn, 
    'SELECT sc.cart_id, sc.product_id, sc.quantity, sc.added_at,
            p.product_name, p.price, p.main_img_name, p.is_available,
            i.quantity as stock_quantity
     FROM shopping_cart sc
     INNER JOIN products p ON sc.product_id = p.product_id
     LEFT JOIN inventory i ON p.product_id = i.product_id
     WHERE sc.user_id = ?
     ORDER BY sc.added_at DESC');

mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$cart_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);

// Calculate totals
$subtotal = 0;
$total_items = 0;

foreach ($cart_items as &$item) {
    $item['subtotal'] = $item['price'] * $item['quantity'];
    $subtotal += $item['subtotal'];
    $total_items += $item['quantity'];
    
    // Get product image
    $imgName = $item['main_img_name'] ?? '';
    $item['image_url'] = $baseUrl . '/assets/default.png';
    
    if (!empty($imgName)) {
        $productImagesDir = __DIR__ . '/../item/products/';
        $extensions = ['.jpg', '.png', '.webp'];
        foreach ($extensions as $ext) {
            $fullPath = $productImagesDir . $imgName . $ext;
            if (file_exists($fullPath)) {
                $item['image_url'] = $baseUrl . '/item/products/' . $imgName . $ext;
                break;
            }
        }
    }
}

$shipping_fee = $subtotal > 0 ? 150.00 : 0; // Flat shipping rate
$total = $subtotal + $shipping_fee;

$pageCss = '';
include __DIR__ . '/../includes/customerHeader.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="cart-page">
    <div class="cart-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Shopping Cart</h1>
            <div class="breadcrumb">
                <a href="<?php echo $baseUrl; ?>/index.php">Home</a>
                <span class="separator">/</span>
                <span>Cart</span>
            </div>
        </div>

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
        <!-- Empty Cart State -->
        <div class="empty-cart">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
            </svg>
            <h2>Your Cart is Empty</h2>
            <p>Looks like you haven't added anything to your cart yet</p>
            <a href="<?php echo $baseUrl; ?>/shop.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M15 18l-6-6 6-6"/>
                </svg>
                Continue Shopping
            </a>
        </div>
        <?php else: ?>
        
        <!-- Cart Grid -->
        <div class="cart-grid">
            <!-- Cart Items Section -->
            <div class="cart-items-section">
                <div class="section-header">
                    <h2 class="section-title">Cart Items</h2>
                    <span class="item-count"><?php echo $total_items; ?> item<?php echo $total_items != 1 ? 's' : ''; ?></span>
                </div>

                <div class="cart-items-list">
                    <?php foreach ($cart_items as $item): ?>
                    <div class="cart-item">
                        <div class="item-image">
                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                            <?php if (!$item['is_available']): ?>
                            <span class="unavailable-badge">Unavailable</span>
                            <?php endif; ?>
                        </div>

                        <div class="item-details">
                            <h3 class="item-name">
                                <a href="../product.php?id=<?php echo $item['product_id']; ?>">
                                    <?php echo htmlspecialchars($item['product_name']); ?>
                                </a>
                            </h3>
                            <div class="item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                            
                            <?php if ($item['stock_quantity'] !== null && $item['stock_quantity'] < 10): ?>
                            <div class="stock-warning">
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                                    <line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                Only <?php echo $item['stock_quantity']; ?> left in stock
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="item-actions">
                            <!-- Quantity Controls -->
                            <form method="POST" action="cart_update.php" class="quantity-form">
                                <input type="hidden" name="type" value="update">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                <input type="hidden" name="redirect" value="view_cart.php">
                                
                                <div class="quantity-controls">
                                    <button type="button" class="qty-btn" onclick="decreaseQty(this, <?php echo $item['cart_id']; ?>)">−</button>
                                    <input type="number" 
                                           name="quantity" 
                                           id="qty-<?php echo $item['cart_id']; ?>"
                                           value="<?php echo $item['quantity']; ?>" 
                                           min="1" 
                                           max="<?php echo $item['stock_quantity'] ?? 999; ?>"
                                           readonly>
                                    <button type="button" class="qty-btn" onclick="increaseQty(this, <?php echo $item['cart_id']; ?>, <?php echo $item['stock_quantity'] ?? 999; ?>)">+</button>
                                </div>
                                
                                <button type="submit" class="btn-update" title="Update quantity">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                    Update
                                </button>
                            </form>

                            <!-- Remove Button -->
                            <form method="POST" action="cart_update.php" class="remove-form">
                                <input type="hidden" name="type" value="remove">
                                <input type="hidden" name="cart_id" value="<?php echo $item['cart_id']; ?>">
                                <input type="hidden" name="redirect" value="view_cart.php">
                                <button type="submit" class="btn-remove" onclick="return confirm('Remove this item from cart?')" title="Remove item">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                        <line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>
                                    </svg>
                                    Remove
                                </button>
                            </form>
                        </div>

                        <div class="item-subtotal">
                            <div class="subtotal-label">Subtotal</div>
                            <div class="subtotal-amount">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Clear Cart Button -->
                <form method="POST" action="cart_update.php" class="clear-cart-form">
                    <input type="hidden" name="type" value="clear">
                    <input type="hidden" name="redirect" value="view_cart.php">
                    <button type="submit" class="btn btn-outline" onclick="return confirm('Are you sure you want to clear your entire cart?')">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Clear Cart
                    </button>
                </form>
            </div>

            <!-- Order Summary Section -->
            <div class="order-summary-section">
                <div class="summary-card">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="summary-row">
                        <span class="summary-label">Subtotal (<?php echo $total_items; ?> items)</span>
                        <span class="summary-value">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    
                    <div class="summary-row">
                        <span class="summary-label">Shipping Fee</span>
                        <span class="summary-value">₱<?php echo number_format($shipping_fee, 2); ?></span>
                    </div>
                    
                    <div class="summary-divider"></div>
                    
                    <div class="summary-row summary-total">
                    <!-- Checkout Button -->
                    <a href="checkout.php" class="btn btn-primary btn-checkout">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M5 12h14"/><path d="M12 5l7 7-7 7"/>
                        </svg>
                        Proceed to Checkout
                    </a>

                    <a href="<?php echo $baseUrl; ?>/shop.php" class="btn btn-outline">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M15 18l-6-6 6-6"/>
                        </svg>
                        Continue Shopping
                    </a>
                </div>

                <!-- Additional Info -->
                <div class="info-card">
                    <h3 class="info-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Secure Checkout
                    </h3>
                    <p class="info-text">Your payment information is encrypted and secure.</p>
                </div>

                <div class="info-card">
                    <h3 class="info-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                        </svg>
                        Payment Methods
                    </h3>
                    <p class="info-text">Cash on Delivery, GCash, Credit Card accepted.</p>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Montserrat', sans-serif;
    background: #ffffff;
    color: #1a1a1a;
    line-height: 1.6;
}

.cart-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.cart-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    margin-bottom: 40px;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 42px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 10px;
}

.breadcrumb {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: rgba(0,0,0,0.5);
}

.breadcrumb a {
    color: rgba(0,0,0,0.6);
    text-decoration: none;
    transition: color 0.3s ease;
}

.breadcrumb a:hover {
    color: #0a0a0a;
}

.breadcrumb .separator {
    color: rgba(0,0,0,0.3);
}

/* Alerts */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px 25px;
    margin-bottom: 30px;
    border-left: 3px solid;
    font-size: 13px;
    letter-spacing: 0.3px;
}

.alert svg {
    flex-shrink: 0;
    margin-top: 2px;
}

.alert-success {
    background: #f0fdf4;
    border-color: #166534;
    color: #166534;
}

.alert-error {
    background: #fef2f2;
    border-color: #b91c1c;
    color: #b91c1c;
}

/* Empty Cart */
.empty-cart {
    text-align: center;
    padding: 80px 20px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
}

.empty-cart svg {
    opacity: 0.15;
    margin-bottom: 30px;
}

.empty-cart h2 {
    font-family: 'Playfair Display', serif;
    font-size: 28px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 10px;
}

.empty-cart p {
    font-size: 14px;
    color: rgba(0,0,0,0.5);
    margin-bottom: 30px;
}

/* Cart Grid */
.cart-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
}

/* Cart Items Section */
.cart-items-section {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    margin-bottom: 25px;
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    color: #0a0a0a;
}

.item-count {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.5px;
}

.cart-items-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 25px;
}

/* Cart Item */
.cart-item {
    display: grid;
    grid-template-columns: 100px 1fr auto 120px;
    gap: 20px;
    padding: 20px;
    border: 1px solid rgba(0,0,0,0.08);
    background: #fafafa;
    transition: all 0.3s ease;
}

.cart-item:hover {
    background: #ffffff;
    box-shadow: 0 3px 10px rgba(0,0,0,0.05);
}

.item-image {
    position: relative;
    width: 100px;
    height: 100px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.unavailable-badge {
    position: absolute;
    top: 5px;
    left: 5px;
    background: #b91c1c;
    color: #ffffff;
    padding: 3px 8px;
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.item-details {
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.item-name {
    font-size: 15px;
    font-weight: 500;
    margin-bottom: 8px;
}

.item-name a {
    color: #0a0a0a;
    text-decoration: none;
    transition: color 0.3s ease;
}

.item-name a:hover {
    color: rgba(0,0,0,0.6);
}

.item-price {
    font-size: 16px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 8px;
}

.stock-warning {
    display: flex;
    align-items: center;
    gap: 5px;
    font-size: 11px;
    color: #b91c1c;
    letter-spacing: 0.3px;
}

.item-actions {
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 10px;
}

.quantity-form {
    display: flex;
    align-items: center;
    gap: 8px;
}

.quantity-controls {
    display: flex;
    align-items: center;
    border: 1px solid rgba(0,0,0,0.15);
}

.qty-btn {
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    cursor: pointer;
    font-size: 16px;
    color: #0a0a0a;
    transition: background 0.3s ease;
}

.qty-btn:hover {
    background: #fafafa;
}

.quantity-controls input {
    width: 50px;
    height: 32px;
    border: none;
    border-left: 1px solid rgba(0,0,0,0.15);
    border-right: 1px solid rgba(0,0,0,0.15);
    text-align: center;
    font-size: 13px;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
}

.btn-update,
.btn-remove {
    display: flex;
    align-items: center;
    gap: 5px;
    padding: 8px 12px;
    border: 1px solid;
    background: transparent;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-update {
    border-color: #0a0a0a;
    color: #0a0a0a;
}

.btn-update:hover {
    background: #0a0a0a;
    color: #ffffff;
}

.btn-remove {
    border-color: #b91c1c;
    color: #b91c1c;
}

.btn-remove:hover {
    background: #b91c1c;
    color: #ffffff;
}

.item-subtotal {
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: flex-end;
}

.subtotal-label {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    margin-bottom: 5px;
}

.subtotal-amount {
    font-size: 18px;
    font-weight: 600;
    color: #0a0a0a;
}

.clear-cart-form {
    padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,0.06);
}

/* Order Summary */
.order-summary-section {
    position: sticky;
    top: 100px;
    height: fit-content;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
}

.summary-title {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.summary-label {
    font-size: 13px;
    color: rgba(0,0,0,0.7);
}

.summary-value {
    font-size: 14px;
    font-weight: 500;
    color: #0a0a0a;
}

.summary-divider {
    height: 1px;
    background: rgba(0,0,0,0.06);
    margin: 20px 0;
}

.summary-total {
    margin-bottom: 25px;
}

.summary-total .summary-label {
    font-size: 15px;
    font-weight: 600;
    color: #0a0a0a;
}

.summary-total .summary-value {
    font-size: 22px;
    font-weight: 600;
    color: #0a0a0a;
}

.info-card {
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 20px;
}

.info-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 10px;
}

.info-text {
    font-size: 12px;
    color: rgba(0,0,0,0.6);
    line-height: 1.6;
}

/* Buttons */
.btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 14px 28px;
    border: 1px solid;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    width: 100%;
    margin-bottom: 10px;
}

.btn-primary {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-primary:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.btn-outline {
    background: transparent;
    border-color: #0a0a0a;
    color: #0a0a0a;
}

.btn-outline:hover {
    background: #0a0a0a;
    color: #ffffff;
}

.btn-checkout {
    margin-bottom: 15px;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .cart-grid {
        grid-template-columns: 1fr;
    }

    .order-summary-section {
        position: relative;
        top: 0;
    }

    .cart-item {
        grid-template-columns: 80px 1fr;
    }

    .item-actions {
        grid-column: 1 / -1;
        flex-direction: row;
        flex-wrap: wrap;
    }

    .item-subtotal {
        grid-column: 1 / -1;
        flex-direction: row;
        justify-content: space-between;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
}

@media (max-width: 768px) {
    .cart-page {
        padding: 80px 20px 50px;
    }

    .page-title {
        font-size: 32px;
    }

    .cart-items-section {
        padding: 20px;
    }

    .cart-item {
        padding: 15px;
    }

    .item-image {
        width: 80px;
        height: 80px;
    }

    .summary-card {
        padding: 25px 20px;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 28px;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .cart-item {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .item-image {
        width: 100%;
        height: 200px;
    }

    .item-actions {
        flex-direction: column;
        align-items: stretch;
    }

    .quantity-form {
        flex-direction: column;
        align-items: stretch;
    }

    .quantity-controls {
        width: 100%;
    }

    .quantity-controls input {
        flex: 1;
    }

    .btn-update,
    .btn-remove {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
// Quantity controls
function increaseQty(btn, cartId, max) {
    const input = document.getElementById('qty-' + cartId);
    const currentValue = parseInt(input.value);
    if (currentValue < max) {
        input.value = currentValue + 1;
    }
}

function decreaseQty(btn, cartId) {
    const input = document.getElementById('qty-' + cartId);
    const currentValue = parseInt(input.value);
    if (currentValue > 1) {
        input.value = currentValue - 1;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>