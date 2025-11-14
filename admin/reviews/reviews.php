<?php
ob_start();
session_start();
// Define the base path
$basePath = dirname(dirname(__DIR__));
require_once($basePath . '/includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to access this page.";
    header("Location: " . $baseUrl . "/admin/login.php");
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
    header("Location: " . $baseUrl . "/admin/dashboard.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle review deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_review'])) {
    $review_id = (int) $_POST['review_id'];
    
    $delete_stmt = $conn->prepare("DELETE FROM reviews WHERE review_id = ?");
    $delete_stmt->bind_param("i", $review_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "Review deleted successfully!";
    } else {
        $error_message = "Error deleting review.";
    }
    $delete_stmt->close();
}

// Get filter parameters
$rating_filter = $_GET['rating'] ?? 'all';
$product_filter = $_GET['product'] ?? 'all';
$search_query = $_GET['search'] ?? '';

// Build query - Admin sees all reviews
$sql = "SELECT 
    r.review_id,
    r.customer_id,
    r.product_id,
    r.rating,
    r.review_text,
    r.created_at,
    r.updated_at,
    c.fullname as customer_name,
    p.product_name,
    p.main_img_name
FROM reviews r
JOIN customers c ON r.customer_id = c.customer_id
JOIN products p ON r.product_id = p.product_id
WHERE 1=1";

$params = [];
$types = "";

if ($rating_filter !== 'all') {
    $sql .= " AND r.rating = ?";
    $params[] = (int)$rating_filter;
    $types .= "i";
}

if ($product_filter !== 'all') {
    $sql .= " AND r.product_id = ?";
    $params[] = (int)$product_filter;
    $types .= "i";
}

if (!empty($search_query)) {
    $sql .= " AND (p.product_name LIKE ? OR r.review_text LIKE ? OR c.fullname LIKE ?)";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$sql .= " ORDER BY r.created_at DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reviews_result = $stmt->get_result();
$stmt->close();

// Get review statistics
$stats_query = "SELECT 
    COUNT(*) as total_reviews,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star,
    SUM(CASE WHEN rating = 4 THEN 1 ELSE 0 END) as four_star,
    SUM(CASE WHEN rating = 3 THEN 1 ELSE 0 END) as three_star,
    SUM(CASE WHEN rating = 2 THEN 1 ELSE 0 END) as two_star,
    SUM(CASE WHEN rating = 1 THEN 1 ELSE 0 END) as one_star
FROM reviews";
$stats_result = $conn->query($stats_query);
$stats = $stats_result->fetch_assoc();

// Get all products for filter dropdown
$products_stmt = $conn->query("SELECT product_id, product_name FROM products ORDER BY product_name ASC");

require_once($basePath . '/includes/adminHeader.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="reviews-page">
    <div class="reviews-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <a href="<?php echo $baseUrl; ?>/admin/dashboard.php" class="back-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="page-title">Review Management</h1>
                <p class="page-subtitle">Monitor and manage all customer product reviews</p>
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

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_reviews']); ?></div>
                    <div class="stat-label">Total Reviews</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-rating">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['avg_rating'], 1); ?></div>
                    <div class="stat-label">Average Rating</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-positive">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 9V5a3 3 0 0 0-3-3l-4 9v11h11.28a2 2 0 0 0 2-1.7l1.38-9a2 2 0 0 0-2-2.3zM7 22H4a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2h3"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo $stats['five_star']; ?></div>
                    <div class="stat-label">5-Star Reviews</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-negative">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M10 15v4a3 3 0 0 0 3 3l4-9V2H5.72a2 2 0 0 0-2 1.7l-1.38 9a2 2 0 0 0 2 2.3zm7-13h2.67A2.31 2.31 0 0 1 22 4v7a2.31 2.31 0 0 1-2.33 2H17"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo ($stats['one_star'] + $stats['two_star']); ?></div>
                    <div class="stat-label">Low Ratings (1-2★)</div>
                </div>
            </div>
        </div>

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
                        placeholder="Product, customer, or review text..."
                        value="<?php echo htmlspecialchars($search_query); ?>"
                    >
                </div>

                <div class="filter-group">
                    <label for="product" class="filter-label">Product</label>
                    <select id="product" name="product" class="filter-input">
                        <option value="all">All Products</option>
                        <?php while ($product = $products_stmt->fetch_assoc()): ?>
                        <option value="<?php echo $product['product_id']; ?>" <?php echo $product_filter == $product['product_id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($product['product_name']); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="rating" class="filter-label">Rating</label>
                    <select id="rating" name="rating" class="filter-input">
                        <option value="all" <?php echo $rating_filter === 'all' ? 'selected' : ''; ?>>All Ratings</option>
                        <option value="5" <?php echo $rating_filter === '5' ? 'selected' : ''; ?>>5 Stars</option>
                        <option value="4" <?php echo $rating_filter === '4' ? 'selected' : ''; ?>>4 Stars</option>
                        <option value="3" <?php echo $rating_filter === '3' ? 'selected' : ''; ?>>3 Stars</option>
                        <option value="2" <?php echo $rating_filter === '2' ? 'selected' : ''; ?>>2 Stars</option>
                        <option value="1" <?php echo $rating_filter === '1' ? 'selected' : ''; ?>>1 Star</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Filter
                </button>

                <?php if ($rating_filter !== 'all' || $product_filter !== 'all' || !empty($search_query)): ?>
                <a href="<?php echo $baseUrl; ?>/admin/reviews/reviews.php" class="btn btn-clear">Clear Filters</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Reviews Grid -->
        <div class="reviews-grid">
            <?php if ($reviews_result->num_rows > 0): ?>
                <?php while ($review = $reviews_result->fetch_assoc()): ?>
                <div class="review-card">
                    <div class="review-header">
                        <div class="product-info">
                            <img 
                                src="<?php echo $baseUrl; ?>/assets/images/products/<?php echo htmlspecialchars($review['main_img_name']); ?>.jpg" 
                                alt="<?php echo htmlspecialchars($review['product_name']); ?>"
                                class="product-thumb"
                                onerror="this.src='<?php echo $baseUrl; ?>/assets/images/products/placeholder.jpg'"
                            >
                            <div class="product-details">
                                <h3 class="product-name"><?php echo htmlspecialchars($review['product_name']); ?></h3>
                                <p class="reviewer-name">by <?php echo htmlspecialchars($review['customer_name']); ?></p>
                            </div>
                        </div>
                        <div class="rating-display">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>" width="18" height="18" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                            <?php endfor; ?>
                        </div>
                    </div>

                    <div class="review-content">
                        <p class="review-text"><?php echo nl2br(htmlspecialchars($review['review_text'])); ?></p>
                    </div>

                    <div class="review-footer">
                        <div class="review-meta">
                            <span class="review-date">
                                <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                            </span>
                            <?php if ($review['created_at'] !== $review['updated_at']): ?>
                                <span class="review-edited">(Edited)</span>
                            <?php endif; ?>
                            <span class="review-id">Review #<?php echo $review['review_id']; ?></span>
                        </div>

                        <div class="review-actions">
                            <button class="btn-action btn-view" onclick="viewReviewDetails(<?php echo $review['review_id']; ?>)">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>
                                </svg>
                                View
                            </button>
                            <form method="POST" action="" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this review? This action cannot be undone.');">
                                <input type="hidden" name="review_id" value="<?php echo $review['review_id']; ?>">
                                <input type="hidden" name="delete_review" value="1">
                                <button type="submit" class="btn-action btn-delete">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
            <div class="no-reviews">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                </svg>
                <h3>No Reviews Found</h3>
                <p>No reviews match your search criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- View Review Details Modal -->
<div id="reviewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title">Review Details</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="reviewDetailsContent">
            <!-- Content will be loaded here -->
        </div>
    </div>
</div>

<?php require_once($basePath . '/includes/footer.php'); ?>

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

.reviews-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.reviews-container {
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

/* Statistics Cards */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: box-shadow 0.3s ease;
}

.stat-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}

.stat-icon {
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.05);
    flex-shrink: 0;
}

.stat-icon svg {
    color: rgba(0,0,0,0.7);
}

.stat-icon-rating {
    background: #fef3c7;
}

.stat-icon-rating svg {
    color: #f59e0b;
}

.stat-icon-positive {
    background: #dcfce7;
}

.stat-icon-positive svg {
    color: #166534;
}

.stat-icon-negative {
    background: #fee2e2;
}

.stat-icon-negative svg {
    color: #b91c1c;
}

.stat-content {
    flex: 1;
}

.stat-value {
    font-size: 28px;
    font-weight: 600;
    color: #0a0a0a;
    line-height: 1.2;
}

.stat-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    margin-top: 5px;
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
    grid-template-columns: 2fr 1.5fr 1fr auto auto;
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 500;
}

.filter-input {
    padding: 12px 16px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 13px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
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
    background: transparent;
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

/* Reviews Grid */
.reviews-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(500px, 1fr));
    gap: 25px;
}

.review-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    transition: box-shadow 0.3s ease;
}

.review-card:hover {
    box-shadow: 0 4px 20px rgba(0,0,0,0.06);
}

.review-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 15px;
}

.product-info {
    display: flex;
    gap: 15px;
    flex: 1;
}

.product-thumb {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border: 1px solid rgba(0,0,0,0.08);
}

.product-details {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.product-name {
    font-size: 14px;
    font-weight: 600;
    color: #0a0a0a;
    line-height: 1.4;
}

.reviewer-name {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
}

.rating-display {
    display: flex;
    gap: 3px;
}

.star {
    color: rgba(0,0,0,0.15);
    transition: color 0.2s ease;
}

.star.filled {
    color: #f59e0b;
}

.review-content {
    flex: 1;
}

.review-text {
    font-size: 13px;
    line-height: 1.8;
    color: rgba(0,0,0,0.8);
}

.review-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-top: 15px;
    border-top: 1px solid rgba(0,0,0,0.05);
}

.review-meta {
    display: flex;
    gap: 15px;
    align-items: center;
    flex-wrap: wrap;
}

.review-date {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
}

.review-edited {
    font-size: 10px;
    color: rgba(0,0,0,0.4);
    font-style: italic;
}

.review-id {
    font-size: 10px;
    color: rgba(0,0,0,0.4);
    font-family: monospace;
}

.review-actions {
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border: 1px solid;
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    background: transparent;
}

.btn-view {
    border-color: rgba(0,0,0,0.15);
    color: #0a0a0a;
}

.btn-view:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

.btn-delete {
    border-color: #b91c1c;
    color: #b91c1c;
}

.btn-delete:hover {
    background: #b91c1c;
    color: #ffffff;
}

/* No Reviews */
.no-reviews {
    grid-column: 1 / -1;
    padding: 80px 40px;
    text-align: center;
}

.no-reviews svg {
    margin-bottom: 20px;
    color: rgba(0,0,0,0.2);
}

.no-reviews h3 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    margin-bottom: 10px;
    color: #0a0a0a;
}

.no-reviews p {
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
    max-width: 700px;
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

    .reviews-grid {
        grid-template-columns: 1fr;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 768px) {
    .reviews-page {
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

    .review-card {
        padding: 25px 20px;
    }

    .review-header {
        flex-direction: column;
    }

    .rating-display {
        align-self: flex-start;
    }

    .review-footer {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .modal-header,
    .modal-body {
        padding: 25px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .stat-card {
        padding: 25px 20px;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 22px;
    }

    .product-info {
        flex-direction: column;
    }

    .product-thumb {
        width: 100%;
        height: 120px;
    }

    .review-actions {
        width: 100%;
        flex-direction: column;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function viewReviewDetails(reviewId) {
    const modal = document.getElementById('reviewModal');
    const content = document.getElementById('reviewDetailsContent');
    
    // Show loading
    content.innerHTML = '<div style="text-align: center; padding: 40px; color: rgba(0,0,0,0.5);">Loading review details...</div>';
    modal.classList.add('show');
    
    // Fetch review details via AJAX
    fetch(`get_review_details.php?review_id=${reviewId}`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = '<div style="text-align: center; padding: 40px; color: #b91c1c;">Error loading review details.</div>';
        });
}

function closeModal() {
    const modal = document.getElementById('reviewModal');
    modal.classList.remove('show');
}

// Close modal when clicking outside
document.getElementById('reviewModal').addEventListener('click', function(e) {
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