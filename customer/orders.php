<?php
require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to view your orders.';
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

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

if (!$customer_id) {
    $_SESSION['error_message'] = 'Customer profile not found.';
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// Handle order cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_order'])) {
    $order_id = (int)$_POST['order_id'];
    
    // Verify order belongs to customer and can be cancelled
    $stmt = mysqli_prepare($conn, 'SELECT order_status, payment_status FROM orders WHERE order_id = ? AND customer_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $order_id, $customer_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if ($result && $order = mysqli_fetch_assoc($result)) {
        $cancellable_statuses = ['Pending', 'Processing'];
        if (in_array($order['order_status'], $cancellable_statuses)) {
            // Cancel the order
            $cancel_stmt = mysqli_prepare($conn, 'UPDATE orders SET order_status = ?, payment_status = ?, updated_at = NOW() WHERE order_id = ?');
$cancelled_status = 'Cancelled';
mysqli_stmt_bind_param($cancel_stmt, 'ssi', $cancelled_status, $cancelled_status, $order_id);
            
            if (mysqli_stmt_execute($cancel_stmt)) {
                $_SESSION['success_message'] = 'Order cancelled successfully.';
            } else {
                $_SESSION['error_message'] = 'Failed to cancel order. Please try again.';
            }
            mysqli_stmt_close($cancel_stmt);
        } else {
            $_SESSION['error_message'] = 'This order cannot be cancelled.';
        }
    } else {
        $_SESSION['error_message'] = 'Order not found.';
    }
    mysqli_stmt_close($stmt);
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Get orders with review counts
$orders = [];
$stmt = mysqli_prepare($conn, 'SELECT o.order_id, o.transaction_id, o.payment_method, 
    o.payment_status, o.order_status, o.total_amount, o.order_date,
    COUNT(DISTINCT oi.order_item_id) as item_count,
    COUNT(DISTINCT r.review_id) as review_count
    FROM orders o
    LEFT JOIN order_items oi ON o.order_id = oi.order_id
    LEFT JOIN reviews r ON oi.product_id = r.product_id AND r.customer_id = ?
    WHERE o.customer_id = ?
    GROUP BY o.order_id
    ORDER BY o.order_date DESC');
mysqli_stmt_bind_param($stmt, 'ii', $customer_id, $customer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $orders = mysqli_fetch_all($res, MYSQLI_ASSOC); }
mysqli_stmt_close($stmt);

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/orders.css">';
include __DIR__ . '/../includes/customerHeader.php';
?>

<main class="orders-page">
    <div class="orders-container">
        <div class="page-header">
            <h1 class="page-title">My Orders</h1>
            <div class="breadcrumb">
                <a href="<?php echo $baseUrl; ?>/index.php">Home</a>
                <span class="separator">/</span>
                <span>Orders</span>
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
                <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
            </svg>
            <span><?php echo htmlspecialchars($_SESSION['error_message']); unset($_SESSION['error_message']); ?></span>
        </div>
        <?php endif; ?>

        <?php if (empty($orders)): ?>
        <!-- No Orders -->
        <div class="empty-orders">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>
                <line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/>
            </svg>
            <h2>No orders yet</h2>
            <p>Start shopping to see your orders here!</p>
            <a href="<?php echo $baseUrl; ?>/shop.php" class="btn btn-primary">
                Browse Products
            </a>
        </div>
        <?php else: ?>
        <!-- Orders List -->
        <div class="orders-list">
            <?php foreach ($orders as $order): 
                $canReview = ($order['order_status'] === 'Delivered' && $order['payment_status'] === 'Paid');
                $hasReviews = (int)$order['review_count'] > 0;
                $allReviewed = $hasReviews && ($order['review_count'] >= $order['item_count']);
                $canCancel = in_array($order['order_status'], ['Pending', 'Processing']);
            ?>
            <div class="order-card">
                <div class="order-card-header">
                    <div class="order-info">
                        <div class="order-number">
                            <span class="label">Order #</span>
                            <span class="value"><?php echo htmlspecialchars($order['transaction_id']); ?></span>
                        </div>
                        <div class="order-date">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <?php echo date('M j, Y g:i A', strtotime($order['order_date'])); ?>
                        </div>
                    </div>
                    <div class="order-badges">
                        <span class="badge badge-<?php echo strtolower($order['payment_status']); ?>">
                            <?php echo htmlspecialchars($order['payment_status']); ?>
                        </span>
                        <span class="badge badge-<?php echo strtolower($order['order_status']); ?>">
                            <?php echo htmlspecialchars($order['order_status']); ?>
                        </span>
                        <?php if ($canReview): ?>
                            <?php if ($allReviewed): ?>
                            <span class="badge badge-reviewed">
                                <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" stroke="none">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                                Reviewed
                            </span>
                            <?php elseif ($hasReviews): ?>
                            <span class="badge badge-partial-review">
                                <?php echo $order['review_count']; ?>/<?php echo $order['item_count']; ?> Reviewed
                            </span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="order-card-body">
                    <div class="order-details">
                        <div class="detail-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                                <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                            </svg>
                            <span><?php echo $order['item_count']; ?> item<?php echo $order['item_count'] != 1 ? 's' : ''; ?></span>
                        </div>
                        <div class="detail-item">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <?php if ($order['payment_method'] === 'Cash on Delivery'): ?>
                                    <line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                <?php else: ?>
                                    <rect x="1" y="4" width="22" height="16" rx="2" ry="2"/><line x1="1" y1="10" x2="23" y2="10"/>
                                <?php endif; ?>
                            </svg>
                            <span><?php echo htmlspecialchars($order['payment_method']); ?></span>
                        </div>
                    </div>

                    <div class="order-total">
                        <span class="total-label">Total</span>
                        <span class="total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></span>
                    </div>
                </div>

                <div class="order-card-footer">
                    <a href="<?php echo $baseUrl; ?>/customer/cart/order_confirmation.php?order_id=<?php echo $order['order_id']; ?>" 
                       class="btn-view-details">
                        View Details
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                    <?php if ($canReview && !$allReviewed): ?>
                    <a href="<?php echo $baseUrl; ?>/customer/cart/order_confirmation.php?order_id=<?php echo $order['order_id']; ?>#order-items" 
                       class="btn-write-review">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        Write Review
                    </a>
                    <?php endif; ?>
                    <?php if ($canCancel): ?>
                    <button type="button" class="btn-cancel-order" 
                            onclick="showCancelModal(<?php echo $order['order_id']; ?>, '<?php echo htmlspecialchars($order['transaction_id']); ?>')">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                        </svg>
                        Cancel Order
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<!-- Cancel Order Modal -->
<div id="cancelModal" class="modal">
    <div class="modal-overlay" onclick="closeCancelModal()"></div>
    <div class="modal-content">
        <div class="modal-header">
            <h3>Cancel Order</h3>
            <button type="button" class="modal-close" onclick="closeCancelModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        
        <div class="modal-body">
            <div class="modal-icon">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                </svg>
            </div>
            <p class="modal-text">Are you sure you want to cancel order <strong id="modalOrderId"></strong>?</p>
            <p class="modal-subtext">This action cannot be undone.</p>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn-modal-cancel" onclick="closeCancelModal()">
                Keep Order
            </button>
            <form method="POST" style="display: inline;" id="cancelOrderForm">
                <input type="hidden" name="order_id" id="cancelOrderId">
                <button type="submit" name="cancel_order" class="btn-modal-confirm">
                    Yes, Cancel Order
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function showCancelModal(orderId, transactionId) {
    document.getElementById('cancelModal').classList.add('active');
    document.getElementById('modalOrderId').textContent = '#' + transactionId;
    document.getElementById('cancelOrderId').value = orderId;
    document.body.style.overflow = 'hidden';
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.remove('active');
    document.body.style.overflow = '';
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeCancelModal();
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>