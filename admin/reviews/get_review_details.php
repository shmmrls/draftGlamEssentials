<?php
session_start();
// Define the base path
$basePath = dirname(dirname(__DIR__));
require_once($basePath . '/includes/config.php');

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

$review_id = (int) ($_GET['review_id'] ?? 0);

if ($review_id <= 0) {
    echo '<div class="error-message">Invalid review ID.</div>';
    exit;
}

// Fetch complete review details
$review_stmt = $conn->prepare("
    SELECT 
        r.review_id,
        r.customer_id,
        r.product_id,
        r.rating,
        r.review_text,
        r.created_at,
        r.updated_at,
        c.fullname as customer_name,
        c.address as customer_address,
        c.contact_no as customer_contact,
        c.town as customer_town,
        u.email as customer_email,
        u.img_name as customer_img,
        u.created_at as customer_joined,
        p.product_name,
        p.price as product_price,
        p.main_img_name,
        cat.category_name
    FROM reviews r
    JOIN customers c ON r.customer_id = c.customer_id
    JOIN users u ON c.user_id = u.user_id
    JOIN products p ON r.product_id = p.product_id
    JOIN categories cat ON p.category_id = cat.category_id
    WHERE r.review_id = ?
");
$review_stmt->bind_param("i", $review_id);
$review_stmt->execute();
$review_result = $review_stmt->get_result();

if ($review_result->num_rows === 0) {
    echo '<div class="error-message">Review not found.</div>';
    exit;
}

$review = $review_result->fetch_assoc();
$review_stmt->close();

// Check if customer has purchased this product
$purchase_stmt = $conn->prepare("
    SELECT 
        o.order_id,
        o.transaction_id,
        o.order_date,
        o.order_status,
        oi.quantity,
        oi.price
    FROM order_items oi
    JOIN orders o ON oi.order_id = o.order_id
    WHERE o.customer_id = ? AND oi.product_id = ?
    ORDER BY o.order_date DESC
    LIMIT 1
");
$purchase_stmt->bind_param("ii", $review['customer_id'], $review['product_id']);
$purchase_stmt->execute();
$purchase_result = $purchase_stmt->get_result();
$purchase = $purchase_result->fetch_assoc();
$purchase_stmt->close();

// Get customer's total reviews
$customer_reviews_stmt = $conn->prepare("SELECT COUNT(*) as total_reviews FROM reviews WHERE customer_id = ?");
$customer_reviews_stmt->bind_param("i", $review['customer_id']);
$customer_reviews_stmt->execute();
$customer_reviews_result = $customer_reviews_stmt->get_result();
$customer_stats = $customer_reviews_result->fetch_assoc();
$customer_reviews_stmt->close();
?>

<style>
.review-detail-section {
    margin-bottom: 30px;
}

.section-title {
    font-size: 11px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 600;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
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

.product-showcase {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
    align-items: center;
}

.product-image {
    width: 100px;
    height: 100px;
    object-fit: cover;
    border: 1px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
}

.product-info-detail {
    flex: 1;
}

.product-title {
    font-size: 16px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 8px;
}

.product-meta {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
    margin-bottom: 5px;
}

.product-price {
    font-size: 18px;
    font-weight: 600;
    color: #166534;
}

.customer-profile {
    display: flex;
    gap: 20px;
    padding: 20px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
    align-items: center;
}

.customer-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(0,0,0,0.08);
    flex-shrink: 0;
}

.customer-info {
    flex: 1;
}

.customer-name {
    font-size: 18px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 5px;
}

.customer-email {
    font-size: 12px;
    color: rgba(0,0,0,0.6);
    margin-bottom: 8px;
}

.customer-stats {
    display: flex;
    gap: 15px;
    margin-top: 10px;
}

.stat-badge {
    padding: 5px 12px;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    font-size: 10px;
    letter-spacing: 0.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
}

.rating-showcase {
    display: flex;
    align-items: center;
    gap: 15px;
    padding: 20px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
}

.rating-number {
    font-size: 48px;
    font-weight: 600;
    color: #0a0a0a;
}

.rating-stars-large {
    display: flex;
    gap: 5px;
}

.star-large {
    color: rgba(0,0,0,0.15);
}

.star-large.filled {
    color: #f59e0b;
}

.review-text-box {
    padding: 25px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
    font-size: 14px;
    line-height: 1.8;
    color: rgba(0,0,0,0.8);
}

.purchase-info {
    padding: 20px;
    background: #dcfce7;
    border: 1px solid #166534;
    border-left: 3px solid #166534;
}

.purchase-info.no-purchase {
    background: #fef3c7;
    border-color: #92400e;
}

.purchase-title {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
    margin-bottom: 10px;
    color: #166534;
}

.purchase-info.no-purchase .purchase-title {
    color: #92400e;
}

.purchase-detail {
    font-size: 12px;
    color: rgba(0,0,0,0.7);
    margin-bottom: 5px;
}

.timestamp-info {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 20px;
    background: #fafafa;
    border: 1px solid rgba(0,0,0,0.05);
}

.timestamp-item {
    display: flex;
    justify-content: space-between;
    font-size: 12px;
}

.timestamp-label {
    color: rgba(0,0,0,0.5);
}

.timestamp-value {
    color: #0a0a0a;
    font-weight: 500;
}

.action-buttons {
    display: flex;
    gap: 15px;
    padding-top: 20px;
    border-top: 1px solid rgba(0,0,0,0.08);
}

.btn-action-modal {
    flex: 1;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 24px;
    border: 1px solid;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    text-decoration: none;
}

.btn-delete-modal {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #ffffff;
}

.btn-delete-modal:hover {
    background: #991b1b;
    border-color: #991b1b;
}

.btn-close-modal {
    background: transparent;
    border-color: rgba(0,0,0,0.15);
    color: #0a0a0a;
}

.btn-close-modal:hover {
    border-color: #0a0a0a;
    background: #fafafa;
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

    .product-showcase,
    .customer-profile {
        flex-direction: column;
        text-align: center;
    }

    .product-image,
    .customer-avatar {
        width: 100%;
        height: 150px;
    }

    .customer-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
    }

    .customer-stats {
        justify-content: center;
    }

    .action-buttons {
        flex-direction: column;
    }
}
</style>

<!-- Review Information -->
<div class="review-detail-section">
    <h3 class="section-title">Review Information</h3>
    <div class="detail-grid">
        <div class="detail-item">
            <span class="detail-label">Review ID</span>
            <span class="detail-value strong">#<?php echo $review['review_id']; ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Status</span>
            <span class="detail-value">
                <?php echo ($review['created_at'] !== $review['updated_at']) ? 'Edited' : 'Original'; ?>
            </span>
        </div>
    </div>
</div>

<!-- Product Information -->
<div class="review-detail-section">
    <h3 class="section-title">Product Reviewed</h3>
    <div class="product-showcase">
        <img 
            src="../assets/images/products/<?php echo htmlspecialchars($review['main_img_name']); ?>.jpg" 
            alt="<?php echo htmlspecialchars($review['product_name']); ?>"
            class="product-image"
            onerror="this.src='../assets/images/products/placeholder.jpg'"
        >
        <div class="product-info-detail">
            <h4 class="product-title"><?php echo htmlspecialchars($review['product_name']); ?></h4>
            <p class="product-meta">Category: <?php echo htmlspecialchars($review['category_name']); ?></p>
            <p class="product-meta">Product ID: #<?php echo $review['product_id']; ?></p>
            <p class="product-price">₱<?php echo number_format($review['product_price'], 2); ?></p>
        </div>
    </div>
</div>

<!-- Customer Information -->
<div class="review-detail-section">
    <h3 class="section-title">Customer Information</h3>
    <div class="customer-profile">
        <img 
            src="<?php echo $baseUrl; ?>/assets/images/users/<?php echo htmlspecialchars($review['customer_img']); ?>" 
            alt="<?php echo htmlspecialchars($review['customer_name']); ?>"
            class="customer-avatar"
            onerror="this.src='<?php echo $baseUrl; ?>/assets/images/users/nopfp.jpg'"
        >
        <div class="customer-info">
            <h4 class="customer-name"><?php echo htmlspecialchars($review['customer_name']); ?></h4>
            <p class="customer-email"><?php echo htmlspecialchars($review['customer_email']); ?></p>
            <div class="customer-stats">
                <span class="stat-badge">Customer ID: #<?php echo $review['customer_id']; ?></span>
                <span class="stat-badge">Total Reviews: <?php echo $customer_stats['total_reviews']; ?></span>
                <span class="stat-badge">Member Since: <?php echo date('M Y', strtotime($review['customer_joined'])); ?></span>
            </div>
        </div>
    </div>

    <div class="detail-grid" style="margin-top: 20px;">
        <div class="detail-item">
            <span class="detail-label">Contact Number</span>
            <span class="detail-value"><?php echo htmlspecialchars($review['customer_contact'] ?: 'Not provided'); ?></span>
        </div>
        <div class="detail-item">
            <span class="detail-label">Location</span>
            <span class="detail-value"><?php echo htmlspecialchars($review['customer_town'] ?: 'Not provided'); ?></span>
        </div>
    </div>
</div>

<!-- Rating -->
<div class="review-detail-section">
    <h3 class="section-title">Rating</h3>
    <div class="rating-showcase">
        <div class="rating-number"><?php echo $review['rating']; ?>/5</div>
        <div class="rating-stars-large">
            <?php for ($i = 1; $i <= 5; $i++): ?>
                <svg class="star-large <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>" width="32" height="32" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
            <?php endfor; ?>
        </div>
    </div>
</div>

<!-- Review Text -->
<div class="review-detail-section">
    <h3 class="section-title">Review Content</h3>
    <div class="review-text-box">
        <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
    </div>
</div>

<!-- Purchase Verification -->
<div class="review-detail-section">
    <h3 class="section-title">Purchase History</h3>
    <?php if ($purchase): ?>
    <div class="purchase-info">
        <div class="purchase-title">✓ Verified Purchase</div>
        <p class="purchase-detail"><strong>Order ID:</strong> #<?php echo $purchase['order_id']; ?></p>
        <p class="purchase-detail"><strong>Transaction ID:</strong> <?php echo htmlspecialchars($purchase['transaction_id']); ?></p>
        <p class="purchase-detail"><strong>Purchase Date:</strong> <?php echo date('F j, Y', strtotime($purchase['order_date'])); ?></p>
        <p class="purchase-detail"><strong>Quantity:</strong> <?php echo $purchase['quantity']; ?> item(s)</p>
        <p class="purchase-detail"><strong>Order Status:</strong> <?php echo $purchase['order_status']; ?></p>
    </div>
    <?php else: ?>
    <div class="purchase-info no-purchase">
        <div class="purchase-title">⚠ No Purchase Record Found</div>
        <p class="purchase-detail">This customer has not purchased this product according to our records.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Timestamps -->
<div class="review-detail-section">
    <h3 class="section-title">Timeline</h3>
    <div class="timestamp-info">
        <div class="timestamp-item">
            <span class="timestamp-label">Review Submitted:</span>
            <span class="timestamp-value"><?php echo date('F j, Y g:i A', strtotime($review['created_at'])); ?></span>
        </div>
        <?php if ($review['created_at'] !== $review['updated_at']): ?>
        <div class="timestamp-item">
            <span class="timestamp-label">Last Updated:</span>
            <span class="timestamp-value"><?php echo date('F j, Y g:i A', strtotime($review['updated_at'])); ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Actions -->
<div class="action-buttons">
    <form method="POST" action="reviews.php" style="flex: 1;" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
        <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
        <input type="hidden" name="delete_review" value="1">
        <button type="submit" class="btn-action-modal btn-delete-modal">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
            </svg>
            Delete Review
        </button>
    </form>
    <button type="button" class="btn-action-modal btn-close-modal" onclick="closeModal()">
        Close
    </button>
</div>