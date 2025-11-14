<?php
/**
 * MP5: Checkout and Order Processing
 * FR4: Checkout and Order Processing
 * FR4.1-FR4.7: Complete checkout flow with prepared statements
 */

require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to proceed with checkout.';
    header('Location: ../user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get customer information
$stmt = mysqli_prepare($conn, 'SELECT customer_id, fullname, address, contact_no, town, zipcode FROM customers WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$customer = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$customer) {
    $_SESSION['error_message'] = 'Please complete your profile before checking out.';
    header('Location: ../user/profile.php');
    exit;
}

$customer_id = (int)$customer['customer_id'];

// Fetch cart items
$stmt = mysqli_prepare($conn, 
    'SELECT sc.cart_id, sc.product_id, sc.quantity,
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

// Redirect if cart is empty
if (empty($cart_items)) {
    $_SESSION['error_message'] = 'Your cart is empty.';
    header('Location: ' . $baseUrl . '/cart/view_cart.php');
    exit;
}

// Check stock availability and calculate totals
$subtotal = 0;
$total_items = 0;
$stock_issues = [];

foreach ($cart_items as &$item) {
    // Check stock availability
    if (!$item['is_available']) {
        $stock_issues[] = $item['product_name'] . ' is no longer available';
    } elseif ($item['stock_quantity'] < $item['quantity']) {
        $stock_issues[] = $item['product_name'] . ' has insufficient stock (only ' . $item['stock_quantity'] . ' available)';
    }
    
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

// If there are stock issues, redirect back to cart
if (!empty($stock_issues)) {
    $_SESSION['error_message'] = implode('. ', $stock_issues) . '. Please update your cart.';
    header('Location: ' . $baseUrl . '/cart/view_cart.php');
    exit;
}

$shipping_fee = 150.00;
$total = $subtotal + $shipping_fee;

$pageCss = '';
include __DIR__ . '/../includes/customerHeader.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="checkout-page">
    <div class="checkout-container">
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">Checkout</h1>
            <div class="breadcrumb">
                <a href="../index.php">Home</a>
                <span class="separator">/</span>
                <a href="view_cart.php">Cart</a>
                <span class="separator">/</span>
                <span>Checkout</span>
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

        <!-- Checkout Grid -->
        <div class="checkout-grid">
            <!-- Checkout Form -->
            <div class="checkout-form-section">
                <form method="POST" action="process_order.php" id="checkoutForm" novalidate>
                    
                    <!-- Shipping Information -->
                    <div class="form-card">
                        <h2 class="form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>
                            </svg>
                            Shipping Information
                        </h2>

                        <div class="form-group">
                            <label for="shipping_name" class="form-label">Full Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="shipping_name" 
                                   name="shipping_name" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['fullname'] ?? ''); ?>"
                                   placeholder="Enter your full name">
                            <span class="form-error" id="name-error"></span>
                        </div>

                        <div class="form-group">
                            <label for="shipping_contact" class="form-label">Contact Number <span class="required">*</span></label>
                            <input type="text" 
                                   id="shipping_contact" 
                                   name="shipping_contact" 
                                   class="form-input" 
                                   value="<?php echo htmlspecialchars($customer['contact_no'] ?? ''); ?>"
                                   placeholder="09XX XXX XXXX">
                            <span class="form-error" id="contact-error"></span>
                        </div>

                        <div class="form-group">
                            <label for="shipping_address" class="form-label">Street Address <span class="required">*</span></label>
                            <textarea id="shipping_address" 
                                      name="shipping_address" 
                                      class="form-textarea" 
                                      rows="3"
                                      placeholder="House/Unit No., Street Name, Barangay"><?php echo htmlspecialchars($customer['address'] ?? ''); ?></textarea>
                            <span class="form-error" id="address-error"></span>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="shipping_town" class="form-label">City/Municipality <span class="required">*</span></label>
                                <input type="text" 
                                       id="shipping_town" 
                                       name="shipping_town" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($customer['town'] ?? ''); ?>"
                                       placeholder="City or Municipality">
                                <span class="form-error" id="town-error"></span>
                            </div>

                            <div class="form-group">
                                <label for="shipping_zipcode" class="form-label">ZIP Code <span class="required">*</span></label>
                                <input type="text" 
                                       id="shipping_zipcode" 
                                       name="shipping_zipcode" 
                                       class="form-input" 
                                       value="<?php echo htmlspecialchars($customer['zipcode'] ?? ''); ?>"
                                       placeholder="1234">
                                <span class="form-error" id="zipcode-error"></span>
                            </div>
                        </div>
                    </div>

                    <!-- Payment Method -->
                    <div class="form-card">
                        <h2 class="form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                            </svg>
                            Payment Method
                        </h2>

                        <div class="payment-options">
                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Cash on Delivery" checked>
                                <div class="payment-card">
                                    <div class="payment-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                        </svg>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Cash on Delivery</div>
                                        <div class="payment-desc">Pay when you receive your order</div>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="GCash">
                                <div class="payment-card">
                                    <div class="payment-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                        </svg>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">GCash</div>
                                        <div class="payment-desc">Pay securely via GCash</div>
                                    </div>
                                </div>
                            </label>

                            <label class="payment-option">
                                <input type="radio" name="payment_method" value="Credit Card">
                                <div class="payment-card">
                                    <div class="payment-icon">
                                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                            <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                        </svg>
                                    </div>
                                    <div class="payment-details">
                                        <div class="payment-name">Credit Card</div>
                                        <div class="payment-desc">Visa, Mastercard, AMEX accepted</div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        <span class="form-error" id="payment-error"></span>
                    </div>

                    <!-- Additional Notes -->
                    <div class="form-card">
                        <h2 class="form-section-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                            </svg>
                            Order Notes (Optional)
                        </h2>

                        <div class="form-group">
                            <textarea id="order_notes" 
                                      name="order_notes" 
                                      class="form-textarea" 
                                      rows="4"
                                      placeholder="Any special instructions for your order?"></textarea>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-place-order">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Place Order
                    </button>
                </form>
            </div>

            <!-- Order Summary -->
            <div class="order-summary-section">
                <div class="summary-card">
                    <h2 class="summary-title">Order Summary</h2>
                    
                    <div class="order-items-preview">
                        <?php foreach ($cart_items as $item): ?>
                        <div class="summary-item">
                            <div class="summary-item-image">
                                <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                     alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                <span class="item-qty"><?php echo $item['quantity']; ?>x</span>
                            </div>
                            <div class="summary-item-details">
                                <div class="summary-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <div class="summary-item-price">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="summary-divider"></div>
                    
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
                        <span class="summary-label">Total</span>
                        <span class="summary-value">₱<?php echo number_format($total, 2); ?></span>
                    </div>
                </div>

                <!-- Security Badge -->
                <div class="info-card">
                    <h3 class="info-title">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                        Secure Checkout
                    </h3>
                    <p class="info-text">Your information is encrypted and secure. We never store your payment details.</p>
                </div>
            </div>
        </div>
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

.checkout-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.checkout-container {
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

/* Checkout Grid */
.checkout-grid {
    display: grid;
    grid-template-columns: 1fr 400px;
    gap: 40px;
}

/* Form Section */
.checkout-form-section {
    display: flex;
    flex-direction: column;
    gap: 25px;
}

.form-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
}

.form-section-title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.form-group {
    margin-bottom: 20px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

.form-label {
    display: block;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 500;
    margin-bottom: 10px;
}

.required {
    color: #b91c1c;
}

.form-input,
.form-textarea {
    width: 100%;
    padding: 14px 18px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 14px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
    transition: all 0.3s ease;
}

.form-input:focus,
.form-textarea:focus {
    outline: none;
    border-color: #0a0a0a;
}

.form-textarea {
    resize: vertical;
}

.form-input::placeholder,
.form-textarea::placeholder {
    color: rgba(0,0,0,0.3);
}

.form-error {
    display: none;
    font-size: 11px;
    color: #b91c1c;
    margin-top: 8px;
    letter-spacing: 0.3px;
}

.form-error.show {
    display: block;
}

/* Payment Options */
.payment-options {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.payment-option {
    cursor: pointer;
}

.payment-option input[type="radio"] {
    display: none;
}

.payment-card {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 18px 20px;
    border: 2px solid rgba(0,0,0,0.08);
    background: #fafafa;
    transition: all 0.3s ease;
}

.payment-option input[type="radio"]:checked + .payment-card {
    border-color: #0a0a0a;
    background: #ffffff;
}

.payment-card:hover {
    border-color: rgba(0,0,0,0.2);
}

.payment-icon {
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
}

.payment-details {
    flex: 1;
}

.payment-name {
    font-size: 14px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 3px;
}

.payment-desc {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
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

.order-items-preview {
    display: flex;
    flex-direction: column;
    gap: 15px;
    margin-bottom: 20px;
}

.summary-item {
    display: flex;
    gap: 12px;
}

.summary-item-image {
    position: relative;
    width: 60px;
    height: 60px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
    overflow: hidden;
}

.summary-item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-qty {
    position: absolute;
    top: -5px;
    right: -5px;
    width: 22px;
    height: 22px;
    background: #0a0a0a;
    color: #ffffff;
    font-size: 10px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
}

.summary-item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    justify-content: center;
}

.summary-item-name {
    font-size: 12px;
    font-weight: 500;
    color: #0a0a0a;
    margin-bottom: 5px;
    line-height: 1.4;
}

.summary-item-price {
    font-size: 13px;
    font-weight: 600;
    color: rgba(0,0,0,0.7);
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
    margin-bottom: 0;
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
}

.btn-primary {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
    width: 100%;
}

.btn-primary:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.btn-place-order {
    margin-top: 10px;
}

/* Responsive Design */
@media (max-width: 1024px) {
    .checkout-grid {
        grid-template-columns: 1fr;
    }

    .order-summary-section {
        position: relative;
        top: 0;
    }

    .form-row {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .checkout-page {
        padding: 80px 20px 50px;
    }

    .page-title {
        font-size: 32px;
    }

    .form-card {
        padding: 25px 20px;
    }

    .summary-card {
        padding: 25px 20px;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 28px;
    }

    .form-section-title {
        font-size: 18px;
    }
}
</style>

<script>
// Quiz 4: Form validation without HTML5 (10pts)
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    let isValid = true;
    
    // Clear previous errors
    document.querySelectorAll('.form-error').forEach(el => {
        el.classList.remove('show');
        el.textContent = '';
    });
    
    // Shipping Name validation
    const shippingName = document.getElementById('shipping_name').value.trim();
    const nameError = document.getElementById('name-error');
    
    if (shippingName === '') {
        nameError.textContent = 'Full name is required';
        nameError.classList.add('show');
        isValid = false;
    } else if (shippingName.length < 3) {
        nameError.textContent = 'Name must be at least 3 characters';
        nameError.classList.add('show');
        isValid = false;
    } else if (!/^[a-zA-Z\s.\-]+$/.test(shippingName)) {
        nameError.textContent = 'Name can only contain letters, spaces, periods, and hyphens';
        nameError.classList.add('show');
        isValid = false;
    }
    
    // Contact Number validation
    const shippingContact = document.getElementById('shipping_contact').value.trim();
    const contactError = document.getElementById('contact-error');
    
    if (shippingContact === '') {
        contactError.textContent = 'Contact number is required';
        contactError.classList.add('show');
        isValid = false;
    } else if (!/^(09|\+639)\d{9}$/.test(shippingContact.replace(/\s/g, ''))) {
        contactError.textContent = 'Please enter a valid Philippine mobile number (09XXXXXXXXX)';
        contactError.classList.add('show');
        isValid = false;
    }
    
    // Address validation
    const shippingAddress = document.getElementById('shipping_address').value.trim();
    const addressError = document.getElementById('address-error');
    
    if (shippingAddress === '') {
        addressError.textContent = 'Address is required';
        addressError.classList.add('show');
        isValid = false;
    } else if (shippingAddress.length < 10) {
        addressError.textContent = 'Please provide a complete address (minimum 10 characters)';
        addressError.classList.add('show');
        isValid = false;
    }
    
    // Town/City validation
    const shippingTown = document.getElementById('shipping_town').value.trim();
    const townError = document.getElementById('town-error');
    
    if (shippingTown === '') {
        townError.textContent = 'City/Municipality is required';
        townError.classList.add('show');
        isValid = false;
    } else if (shippingTown.length < 3) {
        townError.textContent = 'Please enter a valid city/municipality';
        townError.classList.add('show');
        isValid = false;
    }
    
    // ZIP Code validation
    const shippingZipcode = document.getElementById('shipping_zipcode').value.trim();
    const zipcodeError = document.getElementById('zipcode-error');
    
    if (shippingZipcode === '') {
        zipcodeError.textContent = 'ZIP code is required';
        zipcodeError.classList.add('show');
        isValid = false;
    } else if (!/^\d{4}$/.test(shippingZipcode)) {
        zipcodeError.textContent = 'ZIP code must be 4 digits';
        zipcodeError.classList.add('show');
        isValid = false;
    }
    
    // Payment method validation
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
    const paymentError = document.getElementById('payment-error');
    
    if (!paymentMethod) {
        paymentError.textContent = 'Please select a payment method';
        paymentError.classList.add('show');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        // Scroll to first error
        const firstError = document.querySelector('.form-error.show');
        if (firstError) {
            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
});

// Real-time validation feedback
document.getElementById('shipping_contact')?.addEventListener('input', function() {
    const value = this.value.replace(/\s/g, '');
    if (value.length > 0 && !/^(09|\+639)/.test(value)) {
        this.style.borderColor = '#b91c1c';
    } else {
        this.style.borderColor = 'rgba(0,0,0,0.15)';
    }
});

document.getElementById('shipping_zipcode')?.addEventListener('input', function() {
    const value = this.value;
    if (value.length > 0 && !/^\d+$/.test(value)) {
        this.style.borderColor = '#b91c1c';
    } else {
        this.style.borderColor = 'rgba(0,0,0,0.15)';
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>