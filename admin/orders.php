<?php
ob_start();
session_start();
require_once('../includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to access this page.";
    header("Location: login.php");
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

// Only admin can access this page
if ($user_data['role'] !== 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: dashboard.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle order status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $order_id = (int) $_POST['order_id'];
    $new_status = $_POST['order_status'];
    $payment_status = $_POST['payment_status'];
    
    // Validate status values
    $valid_order_statuses = ['Pending', 'Shipped', 'Delivered', 'Cancelled'];
    $valid_payment_statuses = ['Pending', 'Paid', 'Refunded'];
    
    if (in_array($new_status, $valid_order_statuses) && in_array($payment_status, $valid_payment_statuses)) {
        $update_stmt = $conn->prepare("UPDATE orders SET order_status = ?, payment_status = ? WHERE order_id = ?");
        $update_stmt->bind_param("ssi", $new_status, $payment_status, $order_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Order #$order_id status updated successfully!";
            
            // TODO: Send email notification to customer
            // This would integrate with Mailtrap service
        } else {
            $error_message = "Error updating order status.";
        }
        $update_stmt->close();
    } else {
        $error_message = "Invalid status values.";
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$payment_filter = $_GET['payment'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query with filters
$sql = "SELECT 
    o.order_id,
    o.transaction_id,
    o.customer_id,
    c.fullname as customer_name,
    o.shipping_name,
    o.shipping_address,
    o.shipping_contact,
    o.payment_method,
    o.payment_status,
    o.order_status,
    o.total_amount,
    o.order_date,
    COUNT(oi.order_item_id) as item_count
FROM orders o
LEFT JOIN customers c ON o.customer_id = c.customer_id
LEFT JOIN order_items oi ON o.order_id = oi.order_id
WHERE 1=1";

$params = [];
$types = "";

if ($status_filter !== 'all') {
    $sql .= " AND o.order_status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

if ($payment_filter !== 'all') {
    $sql .= " AND o.payment_status = ?";
    $params[] = $payment_filter;
    $types .= "s";
}

if (!empty($search_query)) {
    $sql .= " AND (o.transaction_id LIKE ? OR c.fullname LIKE ? OR o.shipping_name LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " GROUP BY o.order_id ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

require_once('../includes/adminHeader.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="orders-page">
    <div class="orders-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <a href="dashboard.php" class="back-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="page-title">Order Management</h1>
                <p class="page-subtitle">View and manage all customer orders</p>
            </div>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span><?php echo $success_message; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo $error_message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Filters -->
        <div class="filters-container">
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label for="search" class="filter-label">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="filter-input"
                        placeholder="Transaction ID, Customer name..."
                        value="<?php echo htmlspecialchars($search_query); ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="status" class="filter-label">Order Status</label>
                    <select id="status" name="status" class="filter-input">
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Orders</option>
                        <option value="Pending" <?php echo $status_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Shipped" <?php echo $status_filter === 'Shipped' ? 'selected' : ''; ?>>Shipped</option>
                        <option value="Delivered" <?php echo $status_filter === 'Delivered' ? 'selected' : ''; ?>>Delivered</option>
                        <option value="Cancelled" <?php echo $status_filter === 'Cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="payment" class="filter-label">Payment Status</label>
                    <select id="payment" name="payment" class="filter-input">
                        <option value="all" <?php echo $payment_filter === 'all' ? 'selected' : ''; ?>>All Payments</option>
                        <option value="Pending" <?php echo $payment_filter === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo $payment_filter === 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Refunded" <?php echo $payment_filter === 'Refunded' ? 'selected' : ''; ?>>Refunded</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Filter
                </button>

                <?php if ($status_filter !== 'all' || $payment_filter !== 'all' || !empty($search_query)): ?>
                <a href="orders.php" class="btn btn-clear">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Orders Table -->
        <div class="orders-table-container">
            <?php if ($orders_result->num_rows > 0): ?>
            <table class="orders-table">
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Transaction ID</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Total</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($order = $orders_result->fetch_assoc()): ?>
                    <tr>
                        <td class="order-id">#<?php echo $order['order_id']; ?></td>
                        <td class="transaction-id"><?php echo htmlspecialchars($order['transaction_id']); ?></td>
                        <td class="customer-name"><?php echo htmlspecialchars($order['customer_name'] ?? $order['shipping_name']); ?></td>
                        <td class="item-count"><?php echo $order['item_count']; ?> item(s)</td>
                        <td class="total-amount">₱<?php echo number_format($order['total_amount'], 2); ?></td>
                        <td>
                            <span class="badge badge-payment badge-<?php echo strtolower($order['payment_status']); ?>">
                                <?php echo $order['payment_status']; ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-status badge-<?php echo strtolower($order['order_status']); ?>">
                                <?php echo $order['order_status']; ?>
                            </span>
                        </td>
                        <td class="order-date"><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                        <td>
                            <button class="btn-view" onclick="viewOrder(<?php echo $order['order_id']; ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                View
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-orders">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                    <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                </svg>
                <h3>No Orders Found</h3>
                <p>There are no orders matching your criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Order Details Modal -->
<div id="orderModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Order Details</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="orderDetailsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<?php require_once('../includes/footer.php'); ?>

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

.orders-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.orders-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 40px;
    margin-bottom: 30px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    text-decoration: none;
    margin-bottom: 20px;
    transition: color 0.3s ease;
}

.back-link:hover {
    color: #0a0a0a;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 400;
    margin-bottom: 8px;
    color: #0a0a0a;
}

.page-subtitle {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
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

/* Filters */
.filters-container {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 30px;
}

.filters-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto auto;
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
    min-width: 200px;
}

.filter-label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    font-weight: 500;
}

.filter-input {
    padding: 12px 15px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 13px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
    cursor: pointer;
    transition: all 0.3s ease;
}

.filter-input:focus {
    outline: none;
    border-color: #0a0a0a;
    background: #fafafa;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border: 1px solid;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-filter {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-filter:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.btn-clear {
    background: transparent;
    border-color: rgba(0,0,0,0.15);
    color: #0a0a0a;
}

.btn-clear:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

/* Orders Table */
.orders-table-container {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table thead {
    background: #fafafa;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.orders-table th {
    padding: 20px 25px;
    text-align: left;
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 600;
}

.orders-table td {
    padding: 20px 25px;
    font-size: 13px;
    color: #1a1a1a;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.orders-table tbody tr {
    transition: background-color 0.2s ease;
}

.orders-table tbody tr:hover {
    background: #fafafa;
}

.order-id {
    font-weight: 600;
    color: #0a0a0a;
}

.transaction-id {
    font-family: monospace;
    font-size: 12px;
    color: rgba(0,0,0,0.6);
}

.total-amount {
    font-weight: 600;
    color: #166534;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 6px 12px;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    border-radius: 2px;
}

.badge-payment.badge-pending {
    background: #fef3c7;
    color: #92400e;
}

.badge-payment.badge-paid {
    background: #dcfce7;
    color: #166534;
}

.badge-payment.badge-refunded {
    background: #fee2e2;
    color: #991b1b;
}

.badge-status.badge-pending {
    background: #f3f4f6;
    color: #374151;
}

.badge-status.badge-shipped {
    background: #dbeafe;
    color: #1e40af;
}

.badge-status.badge-delivered {
    background: #dcfce7;
    color: #166534;
}

.badge-status.badge-cancelled {
    background: #fee2e2;
    color: #991b1b;
}

/* View Button */
.btn-view {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    border: 1px solid rgba(0,0,0,0.15);
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #0a0a0a;
}

.btn-view:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

/* No Orders */
.no-orders {
    padding: 80px 40px;
    text-align: center;
}

.no-orders svg {
    margin-bottom: 20px;
    color: rgba(0,0,0,0.2);
}

.no-orders h3 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    margin-bottom: 10px;
    color: #0a0a0a;
}

.no-orders p {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    overflow-y: auto;
    padding: 40px 20px;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #ffffff;
    width: 100%;
    max-width: 900px;
    margin: auto;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px 40px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    color: #0a0a0a;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: rgba(0,0,0,0.5);
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: #0a0a0a;
}

.modal-body {
    padding: 40px;
}

/* Responsive */
@media (max-width: 1200px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .orders-page {
        padding: 80px 20px 50px;
    }

    .page-header {
        padding: 30px 25px;
    }

    .page-title {
        font-size: 26px;
    }

    .filters-container {
        padding: 25px 20px;
    }

    .orders-table th,
    .orders-table td {
        padding: 15px;
        font-size: 12px;
    }

    .modal-header,
    .modal-body {
        padding: 25px;
    }
}
</style>

<script>
function viewOrder(orderId) {
    const modal = document.getElementById('orderModal');
    const content = document.getElementById('orderDetailsContent');
    
    // Show loading
    content.innerHTML = '<div style="text-align: center; padding: 40px;">Loading...</div>';
    modal.classList.add('show');
    
    // Fetch order details via AJAX
    fetch(`get_order_details.php?order_id=${orderId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #b91c1c;">Error loading order details.</div>';
        });
}

function closeModal() {
    const modal = document.getElementById('orderModal');
    modal.classList.remove('show');
}

// Close modal when clicking outside
document.getElementById('orderModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
    }
});
</script>

<?php ob_end_flush(); ?>