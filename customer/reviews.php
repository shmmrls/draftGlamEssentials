<?php
require_once __DIR__ . '/../includes/config.php';
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// Check if user is logged in
if (empty($_SESSION['user_id'])) {
    $_SESSION['error_message'] = 'Please log in to view your reviews.';
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$user_id = (int)$_SESSION['user_id'];

// Get customer ID
$customer_id = 0;
$stmt = mysqli_prepare($conn, 'SELECT customer_id, fullname FROM customers WHERE user_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $customer_id = (int)$row['customer_id'];
    $customer_name = $row['fullname'];
}
mysqli_stmt_close($stmt);

if (!$customer_id) {
    $_SESSION['error_message'] = 'Customer profile not found.';
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// Get reviews with product details
$reviews = [];
$stmt = mysqli_prepare($conn, 'SELECT r.review_id, r.product_id, r.rating, r.review_text, 
    r.created_at, r.updated_at, p.product_name, p.main_img_name, p.price
    FROM reviews r
    INNER JOIN products p ON r.product_id = p.product_id
    WHERE r.customer_id = ?
    ORDER BY r.created_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $customer_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $reviews = mysqli_fetch_all($res, MYSQLI_ASSOC); }
mysqli_stmt_close($stmt);

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/reviews.css">';
include __DIR__ . '/../includes/customerHeader.php';
?>

<main class="reviews-page">
    <div class="reviews-container">
        <div class="page-header">
            <h1 class="page-title">My Reviews</h1>
            <div class="breadcrumb">
                <a href="<?php echo $baseUrl; ?>/index.php">Home</a>
                <span class="separator">/</span>
                <span>Reviews</span>
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

        <?php if (empty($reviews)): ?>
        <!-- No Reviews -->
        <div class="empty-reviews">
            <svg width="120" height="120" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1">
                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
            </svg>
            <h2>No reviews yet</h2>
            <p>Start reviewing products you've purchased!</p>
            <a href="<?php echo $baseUrl; ?>/customer/orders.php" class="btn btn-primary">
                View My Orders
            </a>
        </div>
        <?php else: ?>
        <!-- Reviews List -->
        <div class="reviews-list">
            <?php foreach ($reviews as $review): 
                // Determine product image
                $product_img = $baseUrl . '/assets/default.png';
                $imgName = $review['main_img_name'] ?? '';
                if (!empty($imgName)) {
                    $productImagesDir = __DIR__ . '/../item/products/';
                    $extensions = ['.jpg', '.png', '.webp'];
                    foreach ($extensions as $ext) {
                        $fullPath = $productImagesDir . $imgName . $ext;
                        if (file_exists($fullPath)) { 
                            $product_img = $baseUrl . '/item/products/' . $imgName . $ext; 
                            break; 
                        }
                    }
                }
            ?>
            <div class="review-card">
                <div class="review-card-header">
                    <div class="product-info">
                        <img src="<?php echo htmlspecialchars($product_img); ?>" 
                             alt="<?php echo htmlspecialchars($review['product_name']); ?>"
                             class="product-thumbnail">
                        <div class="product-details">
                            <h3 class="product-name">
                                <a href="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo $review['product_id']; ?>">
                                    <?php echo htmlspecialchars($review['product_name']); ?>
                                </a>
                            </h3>
                            <div class="product-price">₱<?php echo number_format($review['price'], 2); ?></div>
                        </div>
                    </div>
                    <div class="review-rating-display">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" 
                                 fill="<?php echo $i <= (int)$review['rating'] ? '#0a0a0a' : 'none'; ?>" 
                                 stroke="#0a0a0a" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        <?php endfor; ?>
                        <span class="rating-number"><?php echo (int)$review['rating']; ?>.0</span>
                    </div>
                </div>

                <div class="review-card-body">
                    <div class="review-content">
                        <?php echo nl2br(htmlspecialchars($review['review_text'])); ?>
                    </div>
                    <div class="review-meta">
                        <div class="review-date">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/>
                                <line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
                            </svg>
                            <span>Reviewed on <?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                        </div>
                        <?php if ($review['updated_at'] !== $review['created_at']): ?>
                        <div class="review-updated">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="23 4 23 10 17 10"></polyline>
                                <path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path>
                            </svg>
                            <span>Updated <?php echo date('M j, Y', strtotime($review['updated_at'])); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="review-card-footer">
                    <a href="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo $review['product_id']; ?>#reviewForm" 
                       class="btn-edit">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                        </svg>
                        Edit Review
                    </a>
                    <a href="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo $review['product_id']; ?>" 
                       class="btn-view">
                        View Product
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>
                        </svg>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>