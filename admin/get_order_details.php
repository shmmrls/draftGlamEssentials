<?php
session_start();
require_once('../includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo '<div class="error-message">Unauthorized access.</div>';
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user role
$user_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

if ($user_data['role'] !== 'admin') {
    echo '<div class="error-message">Unauthorized access.</div>';
    exit;
}

$order_id = (int) ($_GET['order_id'] ?? 0);

if ($order_id <= 0) {
    echo '<div class="error-message">Invalid order ID.</div>';
    exit;
}

// Fetch order details
$order_stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.transaction_id,
        o.customer_id,
        c.fullname as customer_name,
        c.address as customer_address,
        c.contact_no as customer_contact,
        c.town as customer_town,
        o.shipping_name,
        o.shipping_address,
        o.shipping_contact,
        o.payment_method,
        o.payment_status,
        o.order_status,
        o.total_amount,
        o.order_date,
        u.email as customer_email
    FROM orders o
    LEFT JOIN customers c ON o.customer_id = c.customer_id
    LEFT JOIN users u ON c.user_id = u.user_id
    WHERE o.order_id = ?
");
$order_stmt->bind_param("i", $order_id);
$order_stmt->execute();
$order_result = $order_stmt->get_result();

if ($order_result->num_rows === 0) {
    echo '<div class="error-message">Order not found.</div>';
    exit;
}

$order = $order_result->fetch_assoc();
$order_stmt->close();

// Fetch order items
$items_stmt = $conn->prepare("
    SELECT 
        oi.order_item_id,
        oi.product_id,
        p.product_name,
        p.main_img_name,
        oi.quantity,
        oi.price,
        oi.subtotal
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ?
");
$items_stmt->bind_param("i", $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$items_stmt->close();
?>

<style>
.order-detail-section {
    margin-bottom: 30px;
}

.section-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.section-title {
    font-size: 11px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 600;
    margin: 0;
}

.btn-email {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #2563eb;
    border: 1px solid #2563eb;
    color: #ffffff;
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-email:hover {
    background: #1d4ed8;
    border-color: #1d4ed8;
}

.btn-email:disabled {
    background: #9ca3af;
    border-color: #9ca3af;
    cursor: not-allowed;
    opacity: 0.7;
}

.btn-email svg {
    flex-shrink: 0;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.detail-label {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    font-weight: 500;
}

.detail-value {
    font-size: 13px;
    color: #0a0a0a;
}

.detail-value.strong {
    font-weight: 600;
}

.order-items-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-item {
    display: flex;
    gap: 15px;
    padding: 15px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
}

.item-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    flex-shrink: 0;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
}

.item-details {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.item-name {
    font-size: 13px;
    font-weight: 500;
    color: #0a0a0a;
}

.item-meta {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
}

.item-price {
    font-size: 13px;
    font-weight: 600;
    color: #0a0a0a;
    text-align: right;
}

.order-total {
    background: #fafafa;
    padding: 20px;
    margin-top: 20px;
    border: 1px solid rgba(0,0,0,0.08);
}

.total-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
}

.total-row.grand-total {
    border-top: 2px solid rgba(0,0,0,0.15);
    padding-top: 15px;
    margin-top: 10px;
}

.total-label {
    font-size: 12px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 500;
}

.total-label.grand {
    font-size: 13px;
    font-weight: 600;
    color: #0a0a0a;
}

.total-value {
    font-size: 14px;
    font-weight: 600;
    color: #0a0a0a;
}

.total-value.grand {
    font-size: 18px;
    color: #166534;
}

.status-form {
    display: flex;
    flex-direction: column;
    gap: 15px;
    background: #fafafa;
    padding: 20px;
    border: 1px solid rgba(0,0,0,0.08);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.form-group-inline {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.form-label-inline {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 500;
}

.form-input-inline {
    padding: 12px 16px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 13px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
    transition: all 0.3s ease;
}

.form-input-inline:focus {
    outline: none;
    border-color: #0a0a0a;
}

.btn-update {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    background: #0a0a0a;
    border: 1px solid #0a0a0a;
    color: #ffffff;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
}

.btn-update:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.error-message {
    text-align: center;
    padding: 40px;
    color: #b91c1c;
    font-size: 13px;
}

@media (max-width: 768px) {
    .detail-grid {
        grid-template-columns: 1fr;
    }

    .form-row {
        grid-template-columns: 1fr;
    }

    .order-item {
        flex-direction: column;
    }

    .item-image {
        width: 100%;
        height: 120px;
    }

    .section-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }
}
</style>

<!-- Order Information -->
<div class="order-detail-section">
    <div class="section-header">
        <h3 class="section-title">Order Information</h3>
        <?php if (!empty($order['customer_email'])): ?>
        <form id="emailForm" method="POST" action="orders.php" style="margin: 0;">
            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
            <input type="hidden" name="send_email" value="1">
            <button type="submit" class="btn-email">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Send Email
            </button>
        </form>
        <?php else: ?>
        <span style="color: #b91c1c; font-size: 12px;">No customer email available</span>
        <?php endif; ?>
    </div>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Order ID</span>
            <span class="detail-value strong">#<?php echo $order['order_id']; ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Transaction ID</span>
            <span class="detail-value" style="font-family: monospace;"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Order Date</span>
            <span class="detail-value"><?php echo date('F j, Y g:i A', strtotime($order['order_date'])); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Payment Method</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['payment_method']); ?></span>
        </div>
    </div>
</div>

<!-- Customer Information -->
<div class="order-detail-section">
    <h3 class="section-title">Customer Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Customer Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Email</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['customer_email'] ?? 'N/A'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Contact Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['customer_contact'] ?? 'N/A'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['customer_address'] ?? 'N/A'); ?></span>
        </div>
    </div>
</div>

<!-- Shipping Information -->
<div class="order-detail-section">
    <h3 class="section-title">Shipping Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Recipient Name</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['shipping_name']); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Contact Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['shipping_contact']); ?></span>
        </div>
        <div class="detail-item" style="grid-column: 1 / -1;">
            <span class="detail-label">Shipping Address</span>
            <span class="detail-value"><?php echo htmlspecialchars($order['shipping_address']); ?></span>
        </div>
    </div>
</div>

<!-- Order Items -->
<div class="order-detail-section">
    <h3 class="section-title">Order Items</h3>
    <div class="order-items-list">
        <?php while ($item = $items_result->fetch_assoc()): ?>
        <div class="order-item">
            <img 
                src="../item/products/<?php echo htmlspecialchars($item['main_img_name']); ?>.png" 
                alt="<?php echo htmlspecialchars($item['product_name']); ?>"
                class="item-image"
                onerror="this.src='../assets/nopfp.jpg'"
            >
            <div class="item-details">
                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                <div class="item-meta">
                    Quantity: <?php echo $item['quantity']; ?> × ₱<?php echo number_format($item['price'], 2); ?>
                </div>
            </div>
            <div class="item-price">
                ₱<?php echo number_format($item['subtotal'], 2); ?>
            </div>
        </div>
        <?php endwhile; ?>
    </div>

    <div class="order-total">
        <div class="total-row grand-total">
            <span class="total-label grand">Total Amount</span>
            <span class="total-value grand">₱<?php echo number_format($order['total_amount'], 2); ?></span>
        </div>
    </div>
</div>

<!-- Update Status -->
<div class="order-detail-section">
    <h3 class="section-title">Update Order Status</h3>
    <form id="orderStatusForm" method="POST" action="orders.php" class="status-form">
        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
        <input type="hidden" name="update_status" value="1">
        
        <div class="form-row">
            <div class="form-group-inline">
                <label for="order_status" class="form-label-inline">Order Status</label>
                <select id="order_status" name="order_status" class="form-input-inline">
                    <option value="">Select Status</option>
                    <option value="Pending" <?php echo $order['order_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Shipped" <?php echo $order['order_status'] === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                    <option value="Delivered" <?php echo $order['order_status'] === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                    <option value="Cancelled" <?php echo $order['order_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div class="form-group-inline">
                <label for="payment_status" class="form-label-inline">Payment Status</label>
                <select id="payment_status" name="payment_status" class="form-input-inline">
                    <option value="">Select Status</option>
                    <option value="Pending" <?php echo $order['payment_status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="Paid" <?php echo $order['payment_status'] === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                    <option value="Refunded" <?php echo $order['payment_status'] === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                    <option value="Cancelled" <?php echo $order['payment_status'] === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
        </div>

        <button type="submit" class="btn-update">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            Update Status
        </button>
    </form>
</div>

<script>
// Disable HTML5 validation
document.getElementById('orderStatusForm').setAttribute('novalidate', 'novalidate');

// Form validation for status update
document.getElementById('orderStatusForm').addEventListener('submit', function(e) {
    let isValid = true;
    const orderStatus = document.getElementById('order_status');
    const paymentStatus = document.getElementById('payment_status');
    
    // Clear previous errors
    document.querySelectorAll('.error-message').forEach(el => el.remove());
    document.querySelectorAll('.form-input-inline').forEach(el => {
        el.style.borderColor = 'rgba(0,0,0,0.15)';
    });
    
    // Validate order status
    if (orderStatus.value === '') {
        showValidationError(orderStatus, 'Please select an order status');
        isValid = false;
    }
    
    // Validate payment status
    if (paymentStatus.value === '') {
        showValidationError(paymentStatus, 'Please select a payment status');
        isValid = false;
    }
    
    if (!isValid) {
        e.preventDefault();
        e.stopPropagation();
    }
});

// Email form submission handler
document.getElementById('emailForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const button = this.querySelector('.btn-email');
    const originalContent = button.innerHTML;
    
    // Disable button and show loading
    button.disabled = true;
    button.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" opacity="0.3"/><path d="M12 2 A10 10 0 0 1 22 12" stroke-linecap="round"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></path></svg> Sending...';
    
    // Submit form
    this.submit();
});

function showValidationError(input, message) {
    input.style.borderColor = '#b91c1c';
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.color = '#b91c1c';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';
    errorDiv.style.textAlign = 'left';
    input.parentNode.appendChild(errorDiv);
}
</script>