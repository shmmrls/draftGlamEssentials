<?php
/**
 * Order Confirmation Page
 * Displays order details after successful checkout
 */

require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    header('Location: ../user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

if ($order_id <= 0) {
    header('Location: ../index.php');
    exit;
}

// Fetch order details with customer verification
$stmt = mysqli_prepare($conn, 
    'SELECT o.order_id, o.transaction_id, o.shipping_name, o.shipping_address, 
            o.shipping_contact, o.payment_method, o.payment_status, o.order_status, 
            o.total_amount, o.order_date, c.customer_id
     FROM orders o
     INNER JOIN customers c ON o.customer_id = c.customer_id
     WHERE o.order_id = ? AND c.user_id = ?
     LIMIT 1');

mysqli_stmt_bind_param($stmt, 'ii', $order_id, $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order = $result ? mysqli_fetch_assoc($result) : null;
mysqli_stmt_close($stmt);

if (!$order) {
    $_SESSION['error_message'] = 'Order not found.';
    header('Location: ../index.php');
    exit;
}

// Fetch order items
$stmt = mysqli_prepare($conn, 
    'SELECT oi.product_id, oi.quantity, oi.price, oi.subtotal,
            p.product_name, p.main_img_name
     FROM order_items oi
     INNER JOIN products p ON oi.product_id = p.product_id
     WHERE oi.order_id = ?');

mysqli_stmt_bind_param($stmt, 'i', $order_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$order_items = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : [];
mysqli_stmt_close($stmt);

// Get product images
foreach ($order_items as &$item) {
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

// Calculate breakdown
$shipping_fee = 150.00;
$subtotal = $order['total_amount'] - $shipping_fee;

$pageCss = '';
include __DIR__ . '/../includes/customerHeader.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="confirmation-page">
    <div class="confirmation-container">
        <!-- Success Header -->
        <div class="success-header">
            <div class="success-icon">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                    <polyline points="22 4 12 14.01 9 11.01"/>
                </svg>
            </div>
            <h1 class="success-title">Order Placed Successfully!</h1>
            <p class="success-message">Thank you for your order. We'll send you a confirmation email shortly.</p>
        </div>

        <!-- Order Information Grid -->
        <div class="order-info-grid">
            <!-- Order Details Card -->
            <div class="info-card">
                <h2 class="card-title">Order Details</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Transaction ID</span>
                    <span class="detail-value transaction-id"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Order Date</span>
                    <span class="detail-value"><?php echo date('F j, Y - g:i A', strtotime($order['order_date'])); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Payment Method</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Payment Status</span>
                    <span class="status-badge status-<?php echo strtolower($order['payment_status']); ?>">
                        <?php echo htmlspecialchars($order['payment_status']); ?>
                    </span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Order Status</span>
                    <span class="status-badge status-<?php echo strtolower($order['order_status']); ?>">
                        <?php echo htmlspecialchars($order['order_status']); ?>
                    </span>
                </div>
            </div>

            <!-- Shipping Details Card -->
            <div class="info-card">
                <h2 class="card-title">Shipping Information</h2>
                
                <div class="detail-row">
                    <span class="detail-label">Recipient Name</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Contact Number</span>
                    <span class="detail-value"><?php echo htmlspecialchars($order['shipping_contact']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Delivery Address</span>
                    <span class="detail-value"><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></span>
                </div>
            </div>
        </div>

        <!-- Order Items Section -->
        <div class="order-items-section">
            <h2 class="section-title">Order Items</h2>
            
            <div class="items-list">
                <?php foreach ($order_items as $item): ?>
                <div class="order-item">
                    <div class="item-image">
                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                             alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                    </div>
                    
                    <div class="item-details">
                        <h3 class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></h3>
                        <div class="item-meta">
                            <span class="item-quantity">Qty: <?php echo $item['quantity']; ?></span>
                            <span class="item-price">₱<?php echo number_format($item['price'], 2); ?> each</span>
                        </div>
                    </div>
                    
                    <div class="item-total">
                        ₱<?php echo number_format($item['subtotal'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Order Summary -->
            <div class="order-summary">
                <div class="summary-row">
                    <span class="summary-label">Subtotal</span>
                    <span class="summary-value">₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                
                <div class="summary-row">
                    <span class="summary-label">Shipping Fee</span>
                    <span class="summary-value">₱<?php echo number_format($shipping_fee, 2); ?></span>
                </div>
                
                <div class="summary-divider"></div>
                
                <div class="summary-row summary-total">
                    <span class="summary-label">Total Amount</span>
                    <span class="summary-value">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-buttons">
            <a href="../index.php" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                </svg>
                Back to Home
            </a>
            
            <a href="../shop.php" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                Continue Shopping
            </a>
        </div>

        <!-- Help Section -->
        <div class="help-section">
            <div class="help-icon">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/>
                    <path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/>
                    <line x1="12" y1="17" x2="12.01" y2="17"/>
                </svg>
            </div>
            <div class="help-content">
                <h3 class="help-title">Need Help?</h3>
                <p class="help-text">If you have any questions about your order, please don't hesitate to contact us.</p>
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

.confirmation-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #f0fdf4 0%, #ffffff 100%);
}

.confirmation-container {
    max-width: 1000px;
    margin: 0 auto;
}

/* Success Header */
.success-header {
    text-align: center;
    padding: 40px 20px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    margin-bottom: 40px;
}

.success-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    background: #f0fdf4;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.success-icon svg {
    stroke: #166534;
}

.success-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 10px;
}

.success-message {
    font-size: 14px;
    color: rgba(0,0,0,0.6);
}

/* Order Info Grid */
.order-info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 25px;
    margin-bottom: 40px;
}

.info-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
}

.card-title {
    font-family: 'Playfair Display', serif;
    font-size: 20px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.detail-row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 18px;
    gap: 20px;
}

.detail-row:last-child {
    margin-bottom: 0;
}

.detail-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    font-weight: 500;
    flex-shrink: 0;
}

.detail-value {
    font-size: 13px;
    color: #0a0a0a;
    text-align: right;
}

.transaction-id {
    font-weight: 600;
    font-family: 'Courier New', monospace;
    letter-spacing: 0.5px;
}

.status-badge {
    display: inline-block;
    padding: 6px 12px;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
    border-radius: 2px;
}

.status-pending {
    background: #fef3c7;
    color: #92400e;
}

.status-paid {
    background: #d1fae5;
    color: #065f46;
}

.status-shipped {
    background: #dbeafe;
    color: #1e40af;
}

.status-delivered {
    background: #d1fae5;
    color: #065f46;
}

/* Order Items Section */
.order-items-section {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 40px;
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    color: #0a0a0a;
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.items-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
    margin-bottom: 30px;
}

.order-item {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 20px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.06);
}

.item-image {
    width: 80px;
    height: 80px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
    overflow: hidden;
}

.item-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.item-details {
    flex: 1;
}

.item-name {
    font-size: 15px;
    font-weight: 500;
    color: #0a0a0a;
    margin-bottom: 8px;
}

.item-meta {
    display: flex;
    gap: 15px;
    font-size: 12px;
    color: rgba(0,0,0,0.6);
}

.item-total {
    font-size: 18px;
    font-weight: 600;
    color: #0a0a0a;
}

/* Order Summary */
.order-summary {
    padding-top: 25px;
    border-top: 2px solid rgba(0,0,0,0.06);
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
    padding-top: 10px;
}

.summary-total .summary-label {
    font-size: 16px;
    font-weight: 600;
    color: #0a0a0a;
}

.summary-total .summary-value {
    font-size: 24px;
    font-weight: 600;
    color: #0a0a0a;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 20px;
    margin-bottom: 40px;
}

.btn {
    flex: 1;
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

/* Help Section */
.help-section {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 25px 30px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.08);
}

.help-icon {
    width: 50px;
    height: 50px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.help-icon svg {
    stroke: rgba(0,0,0,0.6);
}

.help-content {
    flex: 1;
}

.help-title {
    font-size: 15px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 5px;
}

.help-text {
    font-size: 13px;
    color: rgba(0,0,0,0.6);
    line-height: 1.6;
}

/* Responsive Design */
@media (max-width: 768px) {
    .confirmation-page {
        padding: 80px 20px 50px;
    }

    .order-info-grid {
        grid-template-columns: 1fr;
    }

    .success-title {
        font-size: 26px;
    }

    .info-card,
    .order-items-section {
        padding: 25px 20px;
    }

    .action-buttons {
        flex-direction: column;
    }

    .order-item {
        flex-wrap: wrap;
    }

    .item-total {
        width: 100%;
        text-align: right;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.06);
    }
}

@media (max-width: 480px) {
    .success-title {
        font-size: 22px;
    }

    .detail-row {
        flex-direction: column;
        align-items: flex-start;
        gap: 5px;
    }

    .detail-value {
        text-align: left;
    }

    .help-section {
        flex-direction: column;
        text-align: center;
    }
}
</style>

<?php include __DIR__ . '/../includes/footer.php'; ?>