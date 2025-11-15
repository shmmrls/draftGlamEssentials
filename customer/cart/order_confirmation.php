<?php
require_once __DIR__ . '/../../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// Get customer ID
$customer_id = 0;
$stmt = mysqli_prepare($conn, 'SELECT customer_id FROM customers WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $customer_id = (int)$row['customer_id'];
}
mysqli_stmt_close($stmt);

// Get order details
$order = null;
$stmt = mysqli_prepare($conn, 'SELECT o.order_id, o.transaction_id, o.shipping_name, o.shipping_address, 
    o.shipping_contact, o.payment_method, o.payment_status, o.order_status, o.total_amount, o.order_date
    FROM orders o
    WHERE o.order_id = ? AND o.customer_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $order = mysqli_fetch_assoc($res); }
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['error_message'] = 'Order not found.';
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// Check if order is delivered and paid (eligible for reviews)
$canReview = ($order['order_status'] === 'Delivered' && $order['payment_status'] === 'Paid');

// Check if order is cancelled
$isCancelled = ($order['order_status'] === 'Cancelled' || $order['payment_status'] === 'Cancelled');

// Get order items with review status
$order_items = [];
$stmt = mysqli_prepare($conn, 'SELECT oi.product_id, oi.quantity, oi.price, oi.subtotal,
    p.product_name, p.main_img_name,
    (SELECT COUNT(*) FROM reviews WHERE customer_id = ? AND product_id = oi.product_id) as has_review
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?');
mysqli_stmt_bind_param($stmt, 'ii', $customer_id, $order_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $order_items = mysqli_fetch_all($res, MYSQLI_ASSOC); }
mysqli_stmt_close($stmt);

// Calculate subtotal and shipping fee
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += $item['subtotal'];
}

// Apply same shipping logic as checkout
$shipping_fee = ($subtotal > 0 && $subtotal < 1000) ? 100 : 0;

// Get images for items
foreach ($order_items as &$item) {
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
unset($item);

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/cart.css">';
include __DIR__ . '/../../includes/customerHeader.php';
?>

<style>
@media print {
    body > *:not(.print-receipt) {
        display: none !important;
    }
    
    @page {
        size: 152mm auto;
        margin: 5mm auto;
    }
    
    body {
        margin: 0;
        padding: 0;
        display: flex;
        justify-content: center;
        align-items: flex-start;
    }
    
    html, body {
        height: auto;
    }
    
    .print-receipt {
        display: block !important;
        position: static !important;
        width: 152mm !important;
        max-width: 152mm !important;
        padding: 0 !important;
        margin: 0 auto !important;
        background: white !important;
        box-shadow: none !important;
    }
    
    .receipt-content {
        max-width: 100% !important;
        padding: 8mm !important;
        font-size: 12px !important;
    }
    
    .no-print {
        display: none !important;
    }
    
    .receipt-header {
        text-align: center;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 2px dashed #000;
    }
    
    .receipt-logo {
        max-width: 60mm !important;
        height: auto;
        margin-bottom: 10px !important;
    }
    
    .receipt-title {
        font-size: 20px !important;
        font-weight: 600;
        margin-bottom: 5px !important;
    }
    
    .receipt-subtitle {
        font-size: 11px !important;
        color: #666;
    }
    
    .receipt-section {
        margin-bottom: 15px !important;
        padding-bottom: 12px !important;
        border-bottom: 1px dashed #ccc !important;
    }
    
    .receipt-section-title {
        font-size: 11px !important;
        margin-bottom: 8px !important;
    }
    
    .receipt-info-grid {
        grid-template-columns: 80px 1fr !important;
        gap: 5px !important;
        font-size: 11px !important;
    }
    
    .receipt-items {
        margin: 10px 0 !important;
    }
    
    .receipt-item {
        display: block !important;
        padding: 8px 0 !important;
        border-bottom: 1px dotted #ccc !important;
        font-size: 11px !important;
    }
    
    .receipt-item:first-child {
        border-top: 2px solid #000 !important;
        font-weight: 600 !important;
        padding-top: 8px !important;
        display: grid !important;
        grid-template-columns: 2fr 1fr 1fr 1fr !important;
        font-size: 12px !important;
    }
    
    .receipt-item:not(:first-child) {
        display: block !important;
    }
    
    .receipt-item-name {
        font-weight: 500;
        margin-bottom: 3px;
    }
    
    .receipt-item-details {
        display: flex;
        justify-content: space-between;
        font-size: 10px;
        color: #666;
    }
    
    .receipt-totals {
        margin-top: 15px !important;
        padding-top: 12px !important;
        border-top: 2px solid #000 !important;
    }
    
    .receipt-total-row {
        display: flex;
        justify-content: space-between;
        padding: 5px 0 !important;
        font-size: 12px !important;
    }
    
    .receipt-total-row.grand-total {
        font-size: 16px !important;
        font-weight: 600 !important;
        padding-top: 8px !important;
        border-top: 2px dashed #000 !important;
        margin-top: 8px !important;
    }
    
    .receipt-footer {
        text-align: center;
        margin-top: 15px !important;
        padding-top: 12px !important;
        border-top: 1px dashed #ccc !important;
        font-size: 9px !important;
        color: #666;
    }
    
    .receipt-footer p {
        margin: 3px 0 !important;
    }
}

.print-receipt {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: white;
    z-index: 9999;
    overflow: auto;
    padding: 40px;
}

.print-receipt.active {
    display: block;
}

.receipt-content {
    max-width: 800px;
    margin: 0 auto;
    background: white;
}

.receipt-header {
    text-align: center;
    margin-bottom: 40px;
    padding-bottom: 30px;
    border-bottom: 2px solid #0a0a0a;
}

.receipt-logo {
    max-width: 200px;
    height: auto;
    margin-bottom: 20px;
}

.receipt-title {
    font-family: 'Playfair Display', serif;
    font-size: 36px;
    font-weight: 400;
    margin-bottom: 10px;
    color: #0a0a0a;
}

.receipt-subtitle {
    font-size: 14px;
    color: rgba(0,0,0,0.6);
    letter-spacing: 1px;
    text-transform: uppercase;
}

.receipt-section {
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(0,0,0,0.1);
}

.receipt-section:last-child {
    border-bottom: none;
}

.receipt-section-title {
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    margin-bottom: 15px;
    font-weight: 500;
}

.receipt-info-grid {
    display: grid;
    grid-template-columns: 150px 1fr;
    gap: 10px;
    font-size: 14px;
}

.receipt-info-label {
    color: rgba(0,0,0,0.6);
}

.receipt-info-value {
    color: #0a0a0a;
    font-weight: 500;
}

.receipt-items {
    margin: 20px 0;
}

.receipt-item {
    display: grid;
    grid-template-columns: 1fr 80px 100px 120px;
    gap: 15px;
    padding: 15px 0;
    border-bottom: 1px solid rgba(0,0,0,0.06);
    font-size: 14px;
}

.receipt-item:first-child {
    border-top: 1px solid rgba(0,0,0,0.1);
    padding-top: 15px;
}

.receipt-item-name {
    font-weight: 500;
}

.receipt-item-qty,
.receipt-item-price,
.receipt-item-subtotal {
    text-align: right;
}

.receipt-totals {
    margin-top: 30px;
    padding-top: 20px;
    border-top: 2px solid #0a0a0a;
}

.receipt-total-row {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    font-size: 16px;
}

.receipt-total-row.grand-total {
    font-size: 24px;
    font-weight: 600;
    padding-top: 15px;
    border-top: 1px solid rgba(0,0,0,0.1);
    margin-top: 10px;
}

.receipt-footer {
    text-align: center;
    margin-top: 40px;
    padding-top: 30px;
    border-top: 1px solid rgba(0,0,0,0.1);
    font-size: 12px;
    color: rgba(0,0,0,0.5);
}

.btn-print-receipt {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 32px;
    background: #ffffff;
    border: 1px solid #0a0a0a;
    color: #0a0a0a;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-print-receipt:hover {
    background: #0a0a0a;
    color: #ffffff;
}

.order-summary-breakdown {
    background: rgba(0,0,0,0.02);
    padding: 20px;
    border-radius: 8px;
    margin-top: 20px;
}

.summary-breakdown-row {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    font-size: 15px;
}

.summary-breakdown-row.total {
    border-top: 2px solid rgba(0,0,0,0.1);
    margin-top: 10px;
    padding-top: 15px;
    font-size: 18px;
    font-weight: 600;
}

.summary-breakdown-label {
    color: rgba(0,0,0,0.7);
}

.summary-breakdown-value {
    font-weight: 500;
}

.summary-breakdown-row.total .summary-breakdown-value {
    color: #0a0a0a;
}

.success-icon svg {
    color: #16a34a;
}

.success-icon.cancelled svg {
    color: #dc2626;
}
</style>

<main class="confirmation-page">
    <div class="confirmation-container">
        <div class="success-header">
            <div class="success-icon <?php echo $isCancelled ? 'cancelled' : ''; ?>">
                <?php if ($isCancelled): ?>
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
                <?php else: ?>
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                <?php endif; ?>
            </div>
            <h1 class="success-title">
                <?php 
                if ($isCancelled) {
                    echo 'Order Cancelled';
                } elseif ($order['order_status'] === 'Delivered') {
                    echo 'Order Delivered Successfully!';
                } else {
                    echo 'Order Placed Successfully!';
                }
                ?>
            </h1>
            <p class="success-message">
                <?php if ($isCancelled): ?>
                    This order has been cancelled.
                <?php elseif ($canReview): ?>
                    Your order has been delivered. We'd love to hear your feedback!
                <?php else: ?>
                    Thank you for your order. We'll send you a confirmation email shortly.
                <?php endif; ?>
            </p>
        </div>

        <div class="order-details-card">
            <div class="order-header">
                <div>
                    <div class="order-label">Order Number</div>
                    <div class="order-value"><?php echo htmlspecialchars($order['transaction_id']); ?></div>
                </div>
                <div>
                    <div class="order-label">Order Date</div>
                    <div class="order-value"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></div>
                </div>
                <div>
                    <div class="order-label">Total Amount</div>
                    <div class="order-value">₱<?php echo number_format($order['total_amount'], 2); ?></div>
                </div>
            </div>

            <div class="order-section">
                <h2 class="section-subtitle">Shipping Information</h2>
                <div class="info-grid">
                    <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>
                        </svg>
                        <span><?php echo htmlspecialchars($order['shipping_address']); ?></span>
                    </div>
                    <div class="info-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                        </svg>
                        <span><?php echo htmlspecialchars($order['shipping_contact']); ?></span>
                    </div>
                </div>
            </div>

            <div class="order-section">
                <h2 class="section-subtitle">Payment Information</h2>
                <div class="payment-info">
                    <div class="payment-method-badge">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <?php if ($order['payment_method'] === 'Cash on Delivery'): ?>
                                <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                            <?php else: ?>
                                <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                            <?php endif; ?>
                        </svg>
                        <span><?php echo htmlspecialchars($order['payment_method']); ?></span>
                    </div>
                    <div class="status-badges">
                        <span class="badge badge-<?php echo strtolower($order['payment_status']); ?>">
                            <?php echo htmlspecialchars($order['payment_status']); ?>
                        </span>
                        <span class="badge badge-<?php echo strtolower($order['order_status']); ?>">
                            <?php echo htmlspecialchars($order['order_status']); ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="order-section">
                <h2 class="section-subtitle">Order Items</h2>
                <div class="order-items-list">
                    <?php 
                    foreach ($order_items as $item): 
                        if (!isset($item['product_id']) || !isset($item['product_name'])) {
                            continue;
                        }
                    ?>
                    <div class="order-item">
                        <img src="<?php echo htmlspecialchars($item['image']); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                        <div class="order-item-details">
                            <div class="order-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                            <div class="order-item-meta">
                                <span>Qty: <?php echo $item['quantity']; ?></span>
                                <span>₱<?php echo number_format($item['price'], 2); ?> each</span>
                            </div>
                            
                            <?php if ($canReview): ?>
                            <div class="review-action">
                                <?php if ($item['has_review'] > 0): ?>
                                <a href="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo $item['product_id']; ?>#reviewForm" 
                                   class="btn-review-link reviewed">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                    View/Edit Review
                                </a>
                                <?php else: ?>
                                <a href="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo $item['product_id']; ?>#reviewForm" 
                                   class="btn-review-link">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                    </svg>
                                    Write a Review
                                </a>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="order-item-total">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="order-summary-breakdown">
                    <div class="summary-breakdown-row">
                        <span class="summary-breakdown-label">Subtotal</span>
                        <span class="summary-breakdown-value">₱<?php echo number_format($subtotal, 2); ?></span>
                    </div>
                    <div class="summary-breakdown-row">
                        <span class="summary-breakdown-label">Shipping Fee</span>
                        <span class="summary-breakdown-value"><?php echo $shipping_fee > 0 ? '₱' . number_format($shipping_fee, 2) : 'FREE'; ?></span>
                    </div>
                    <div class="summary-breakdown-row total">
                        <span class="summary-breakdown-label">Total Amount</span>
                        <span class="summary-breakdown-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="confirmation-actions">
            <?php if (!$isCancelled): ?>
            <button onclick="printReceipt()" class="btn-print-receipt">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print Receipt
            </button>
            <?php endif; ?>
            <a href="<?php echo $baseUrl; ?>/customer/orders.php" class="btn btn-primary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                    <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
                </svg>
                View All Orders
            </a>
            <?php if ($canReview): ?>
            <a href="<?php echo $baseUrl; ?>/customer/reviews.php" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                My Reviews
            </a>
            <?php else: ?>
            <a href="<?php echo $baseUrl; ?>/customer/product-list.php" class="btn btn-secondary">
                Continue Shopping
            </a>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if (!$isCancelled): ?>
<div class="print-receipt" id="printReceipt">
    <div class="receipt-content">
        <div class="receipt-header">
            <img src="<?php echo $baseUrl; ?>/assets/logo1.png" alt="Logo" class="receipt-logo">
            <div class="receipt-title">ORDER RECEIPT</div>
            <div class="receipt-subtitle">Thank you for your purchase</div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Order Information</div>
            <div class="receipt-info-grid">
                <div class="receipt-info-label">Order Number:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['transaction_id']); ?></div>
                
                <div class="receipt-info-label">Order Date:</div>
                <div class="receipt-info-value"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></div>
                
                <div class="receipt-info-label">Payment Method:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['payment_method']); ?></div>
                
                <div class="receipt-info-label">Payment Status:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['payment_status']); ?></div>
                
                <div class="receipt-info-label">Order Status:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['order_status']); ?></div>
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Shipping Information</div>
            <div class="receipt-info-grid">
                <div class="receipt-info-label">Recipient:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['shipping_name']); ?></div>
                
                <div class="receipt-info-label">Address:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['shipping_address']); ?></div>
                
                <div class="receipt-info-label">Contact:</div>
                <div class="receipt-info-value"><?php echo htmlspecialchars($order['shipping_contact']); ?></div>
            </div>
        </div>

        <div class="receipt-section">
            <div class="receipt-section-title">Order Items</div>
            <div class="receipt-items">
                <div class="receipt-item" style="font-weight: 600; border-top: 2px solid #0a0a0a;">
                    <div>Product</div>
                    <div style="text-align: right;">Quantity</div>
                    <div style="text-align: right;">Unit Price</div>
                    <div style="text-align: right;">Subtotal</div>
                </div>
                <?php 
                foreach ($order_items as $item): 
                    if (!isset($item['product_id']) || !isset($item['product_name'])) {
                        continue;
                    }
                ?>
                <div class="receipt-item">
                    <div class="receipt-item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                    <div class="receipt-item-qty"><?php echo $item['quantity']; ?></div>
                    <div class="receipt-item-price">₱<?php echo number_format($item['price'], 2); ?></div>
                    <div class="receipt-item-subtotal">₱<?php echo number_format($item['subtotal'], 2); ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="receipt-totals">
            <div class="receipt-total-row">
                <span>Subtotal:</span>
                <span>₱<?php echo number_format($subtotal, 2); ?></span>
            </div>
            <div class="receipt-total-row">
                <span>Shipping Fee:</span>
                <span><?php echo $shipping_fee > 0 ? '₱' . number_format($shipping_fee, 2) : 'FREE'; ?></span>
            </div>
            <div class="receipt-total-row grand-total">
                <span>TOTAL AMOUNT:</span>
                <span>₱<?php echo number_format($order['total_amount'], 2); ?></span>
            </div>
        </div>

        <div class="receipt-footer">
            <p>This is an official receipt for your order.</p>
            <p>For inquiries, please contact our customer service.</p>
            <p style="margin-top: 20px;">Generated on <?php echo date('F j, Y g:i A'); ?></p>
        </div>

        <div class="no-print" style="text-align: center; margin-top: 30px;">
            <button onclick="window.print()" class="btn btn-primary" style="margin-right: 10px;">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/>
                    <rect x="6" y="14" width="12" height="8"/>
                </svg>
                Print
            </button>
            <button onclick="closeReceipt()" class="btn btn-secondary">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
                Close
            </button>
        </div>
    </div>
</div>

<script>
function printReceipt() {
    document.getElementById('printReceipt').classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeReceipt() {
    document.getElementById('printReceipt').classList.remove('active');
    document.body.style.overflow = 'auto';
}

// Close on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeReceipt();
    }
});

// Close on clicking outside
document.getElementById('printReceipt').addEventListener('click', function(e) {
    if (e.target === this) {
        closeReceipt();
    }
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/../../includes/footer.php'; ?>