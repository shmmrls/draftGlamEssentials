<?php
require_once __DIR__ . '/../includes/config.php';
// Get base URL
$baseUrl = rtrim($baseUrl ?? '', '/');
if (session_status() === PHP_SESSION_NONE) { session_start(); }

// MP4: Regex to filter/mask bad words (5pts)
function mask_bad_words($text) {
  $bad = ['fuck','shit','bitch','asshole','bastard','dick','pussy','cunt','slut','whore','damn','hell','piss','cock','fag'];
  $pattern = '/\b(' . implode('|', array_map(function($w){ return preg_quote($w, '/'); }, $bad)) . ')\b/i';
  return preg_replace_callback($pattern, function($m){
    $w = $m[0];
    $len = mb_strlen($w);
    if ($len <= 2) return str_repeat('*', $len);
    $first = mb_substr($w, 0, 1);
    return $first . str_repeat('*', $len - 1);
  }, $text);
}

// Get product id
$productId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($productId <= 0) {
    header('Location: ' . $baseUrl . '/index.php');
    exit;
}

// Resolve logged in user and customer
$user_id = 0;
if (!empty($_SESSION['user_id'])) { 
    $user_id = (int)$_SESSION['user_id'];
}
$customerId = 0;
$customerName = '';
if ($user_id) {
  $stmt = mysqli_prepare($conn, 'SELECT customer_id, fullname FROM customers WHERE user_id = ? LIMIT 1');
  mysqli_stmt_bind_param($stmt, 'i', $user_id);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($res && $row = mysqli_fetch_assoc($res)) {
    $customerId = (int)$row['customer_id'];
    $customerName = $row['fullname'] ?? '';
  }
  mysqli_stmt_close($stmt);
}

// Check if user has purchased this product (MP4 requirement)
$hasPurchased = false;
if ($customerId) {
  $stmt = mysqli_prepare($conn, 
    'SELECT COUNT(*) as purchase_count 
     FROM orders o
     INNER JOIN order_items oi ON o.order_id = oi.order_id
     WHERE o.customer_id = ? AND oi.product_id = ? AND o.order_status = "Delivered"');
  mysqli_stmt_bind_param($stmt, 'ii', $customerId, $productId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($res && $row = mysqli_fetch_assoc($res)) {
    $hasPurchased = (int)$row['purchase_count'] > 0;
  }
  mysqli_stmt_close($stmt);
}


// MP4: Handle review delete (add this BEFORE the review submit handler)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_delete']) && $user_id && $customerId) {
  // Verify that the review belongs to the current user
  $stmt = mysqli_prepare($conn, 'SELECT review_id FROM reviews WHERE customer_id = ? AND product_id = ?');
  mysqli_stmt_bind_param($stmt, 'ii', $customerId, $productId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  $existing = $res ? mysqli_fetch_assoc($res) : null;
  mysqli_stmt_close($stmt);

  if ($existing) {
    // Delete the review
    $stmt = mysqli_prepare($conn, 'DELETE FROM reviews WHERE review_id = ? AND customer_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $existing['review_id'], $customerId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
      $_SESSION['success_message'] = 'Your review has been deleted successfully!';
    } else {
      $_SESSION['error_message'] = 'Failed to delete review. Please try again.';
    }
  } else {
    $_SESSION['error_message'] = 'Review not found or you do not have permission to delete it.';
  }

  header('Location: ' . $baseUrl . '/customer/product.php?id=' . $productId);
  exit;
}

// MP4: Handle review submit/update (5pts each)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['review_submit']) && $user_id && $customerId) {
  // Check if user has purchased the product
  if (!$hasPurchased) {
    $_SESSION['error_message'] = 'You can only review products you have purchased and received.';
    header('Location: ' . $baseUrl . '/customer/product.php?id=' . $productId);
    exit;
  }
  
  $rating = isset($_POST['rating']) ? max(1, min(5, (int)$_POST['rating'])) : 5;
  $review = isset($_POST['review_text']) ? trim($_POST['review_text']) : '';
  
  // Validation
  $errors = [];
  if (empty($review)) {
    $errors[] = 'Review text is required.';
  } elseif (strlen($review) < 10) {
    $errors[] = 'Review must be at least 10 characters long.';
  } elseif (strlen($review) > 1000) {
    $errors[] = 'Review must not exceed 1000 characters.';
  }
  
  if (empty($errors)) {
    $review = mask_bad_words($review);

    // Check if user already has a review for this product
    $stmt = mysqli_prepare($conn, 'SELECT review_id FROM reviews WHERE customer_id = ? AND product_id = ?');
    mysqli_stmt_bind_param($stmt, 'ii', $customerId, $productId);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $existing = $res ? mysqli_fetch_assoc($res) : null;
    mysqli_stmt_close($stmt);

    if ($existing) {
      // Update existing review
      $stmt = mysqli_prepare($conn, 'UPDATE reviews SET rating = ?, review_text = ?, updated_at = NOW() WHERE review_id = ?');
      mysqli_stmt_bind_param($stmt, 'isi', $rating, $review, $existing['review_id']);
      $success = mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
      
      if ($success) {
        $_SESSION['success_message'] = 'Your review has been updated successfully!';
      } else {
        $_SESSION['error_message'] = 'Failed to update review. Please try again.';
      }
    } else {
      // Insert new review
      $stmt = mysqli_prepare($conn, 'INSERT INTO reviews (customer_id, product_id, rating, review_text) VALUES (?,?,?,?)');
      mysqli_stmt_bind_param($stmt, 'iiis', $customerId, $productId, $rating, $review);
      $success = mysqli_stmt_execute($stmt);
      mysqli_stmt_close($stmt);
      
      if ($success) {
        $_SESSION['success_message'] = 'Thank you for your review!';
      } else {
        $_SESSION['error_message'] = 'Failed to submit review. Please try again.';
      }
    }
  } else {
    $_SESSION['error_message'] = implode(' ', $errors);
  }

  header('Location: ' . $baseUrl . '/customer/product.php?id=' . $productId);
  exit;
}

// Load product data with prepared statements
$stmt = mysqli_prepare($conn, 'SELECT p.product_id, p.product_name, p.description, p.price, p.main_img_name, p.is_featured, c.category_name
  FROM products p JOIN categories c ON c.category_id = p.category_id WHERE p.product_id = ? LIMIT 1');
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$product = $res ? mysqli_fetch_assoc($res) : null;
mysqli_stmt_close($stmt);
if (!$product) { header('Location: ' . $baseUrl . '/index.php'); exit; }

// Determine product main image path
$main_img = $baseUrl . '/assets/default.png';
$imgName = $product['main_img_name'] ?? '';
if (!empty($imgName)) {
    $productImagesDir = __DIR__ . '/../item/products/';
    $extensions = ['.jpg', '.png', '.webp'];
    foreach ($extensions as $ext) {
        $fullPath = $productImagesDir . $imgName . $ext;
        if (file_exists($fullPath)) { 
            $main_img = $baseUrl . '/item/products/' . $imgName . $ext; 
            break; 
        }
    }
}

// Load additional product images (MP1: Multiple photos - 20pts)
$additional_images = [];
$stmt = mysqli_prepare($conn, 'SELECT img_name FROM product_images WHERE product_id = ? ORDER BY image_id');
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) {
    while ($img = mysqli_fetch_assoc($res)) {
        $imgPath = null;
        foreach (['.jpg', '.png', '.webp'] as $ext) {
            $fullPath = __DIR__ . '/../item/product_images/' . $img['img_name'] . $ext;
            if (file_exists($fullPath)) {
                $imgPath = $baseUrl . '/item/product_images/' . $img['img_name'] . $ext;
                break;
            }
        }
        if ($imgPath) $additional_images[] = $imgPath;
    }
}
mysqli_stmt_close($stmt);

// Reviews summary
$avg_rating = 0; $total_reviews = 0;
$stmt = mysqli_prepare($conn, 'SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE product_id = ?');
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { 
    $r = $res->fetch_assoc(); 
    $avg_rating = $r['avg_rating'] ? round($r['avg_rating'], 1) : 0; 
    $total_reviews = (int)$r['total_reviews']; 
}
mysqli_stmt_close($stmt);

// Reviews list with prepared statements
$reviews = [];
$stmt = mysqli_prepare($conn, 'SELECT r.review_id, r.rating, r.review_text, r.created_at, c.fullname
  FROM reviews r JOIN customers c ON c.customer_id = r.customer_id
  WHERE r.product_id = ? ORDER BY r.created_at DESC');
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res) { $reviews = mysqli_fetch_all($res, MYSQLI_ASSOC); }
mysqli_stmt_close($stmt);

// Fetch current user's review if any
$userReview = null;
if ($customerId) {
  $stmt = mysqli_prepare($conn, 'SELECT review_id, rating, review_text FROM reviews WHERE customer_id = ? AND product_id = ?');
  mysqli_stmt_bind_param($stmt, 'ii', $customerId, $productId);
  mysqli_stmt_execute($stmt);
  $res = mysqli_stmt_get_result($stmt);
  if ($res) { $userReview = mysqli_fetch_assoc($res); }
  mysqli_stmt_close($stmt);
}

// Check inventory
$in_stock = true;
$stock_quantity = 0;
$stmt = mysqli_prepare($conn, 'SELECT COALESCE(MAX(quantity), 0) as quantity FROM inventory WHERE product_id = ? GROUP BY product_id');
mysqli_stmt_bind_param($stmt, 'i', $productId);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
if ($res && $row = mysqli_fetch_assoc($res)) {
    $stock_quantity = (int)$row['quantity'];
    $in_stock = $stock_quantity > 0;
}
mysqli_stmt_close($stmt);

$pageCss = '<link rel="stylesheet" href="' . $baseUrl . '/customer/css/product.css">';
include __DIR__ . '/../includes/customerHeader.php';
?>

<main class="product-detail-page">
    <div class="product-container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="<?php echo $baseUrl; ?>/customer/index.php">Home</a>
            <span class="separator">/</span>
            <a href="<?php echo $baseUrl; ?>/customer/product-list.php">Shop</a>
            <span class="separator">/</span>
            <span><?php echo htmlspecialchars($product['product_name']); ?></span>
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

        <!-- Product Details Grid -->
        <div class="product-grid">
            <!-- Product Images -->
            <div class="product-images">
                <div class="main-image-wrapper">
                    <img id="mainImage" src="<?php echo htmlspecialchars($main_img); ?>" 
                         alt="<?php echo htmlspecialchars($product['product_name']); ?>"
                         class="main-image">
                    <?php if ($product['is_featured']): ?>
                    <span class="featured-badge">Featured</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($additional_images)): ?>
                <div class="thumbnail-gallery">
                    <div class="thumbnail-item active" onclick="changeImage('<?php echo htmlspecialchars($main_img); ?>', this)">
                        <img src="<?php echo htmlspecialchars($main_img); ?>" alt="Main">
                    </div>
                    <?php foreach ($additional_images as $img): ?>
                    <div class="thumbnail-item" onclick="changeImage('<?php echo htmlspecialchars($img); ?>', this)">
                        <img src="<?php echo htmlspecialchars($img); ?>" alt="Gallery">
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Product Information -->
            <div class="product-info-section">
                <div class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></div>
                <h1 class="product-title"><?php echo htmlspecialchars($product['product_name']); ?></h1>
                
                <!-- Rating Summary -->
                <div class="rating-summary">
                    <div class="stars">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <svg width="18" height="18" viewBox="0 0 24 24" 
                                 fill="<?php echo $i <= $avg_rating ? '#0a0a0a' : 'none'; ?>" 
                                 stroke="#0a0a0a" stroke-width="2">
                                <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                            </svg>
                        <?php endfor; ?>
                    </div>
                    <span class="rating-text">
                        <?php if ($total_reviews > 0): ?>
                            <?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> review<?php echo $total_reviews != 1 ? 's' : ''; ?>)
                        <?php else: ?>
                            No reviews yet
                        <?php endif; ?>
                    </span>
                </div>

                <div class="product-price">₱<?php echo number_format((float)$product['price'], 2); ?></div>

                <!-- Stock Status -->
                <div class="stock-status <?php echo $in_stock ? 'in-stock' : 'out-of-stock'; ?>">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <?php if ($in_stock): ?>
                            <polyline points="20 6 9 17 4 12"/>
                        <?php else: ?>
                            <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                        <?php endif; ?>
                    </svg>
                    <?php echo $in_stock ? "In Stock ($stock_quantity available)" : 'Out of Stock'; ?>
                </div>

                <div class="product-description">
                    <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                </div>

                <!-- Add to Cart Form (Hide for admin) -->
                <?php if (empty($_SESSION['role']) || $_SESSION['role'] !== 'admin'): ?>
                <?php if ($in_stock): ?>
                <form method="POST" action="<?php echo $baseUrl; ?>/customer/cart/cart_update.php" class="add-to-cart-form">
                    <input type="hidden" name="item_id" value="<?php echo (int)$productId; ?>">
                    <input type="hidden" name="type" value="add">
                    <input type="hidden" name="redirect" value="<?php echo $baseUrl; ?>/customer/cart/view_cart.php">
                    
                    <div class="quantity-selector">
                        <label for="quantity">Quantity</label>
                        <div class="quantity-controls">
                            <button type="button" class="qty-btn" onclick="decreaseQty()">−</button>
                            <input type="number" id="quantity" name="quantity" min="1" max="<?php echo $stock_quantity; ?>" value="1" readonly>
                            <button type="button" class="qty-btn" onclick="increaseQty(<?php echo $stock_quantity; ?>)">+</button>
                        </div>
                    </div>
                    
                    <button class="btn btn-primary" type="submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        Add to Cart
                    </button>
                </form>
                <?php else: ?>
                <div class="out-of-stock-notice">
                    <p>This product is currently out of stock. Please check back later.</p>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Reviews Section -->
        <section class="reviews-section">
            <div class="reviews-header">
                <h2 class="section-title">Customer Reviews</h2>
                <div class="review-stats">
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $avg_rating; ?></span>
                        <span class="stat-label">Average Rating</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-value"><?php echo $total_reviews; ?></span>
                        <span class="stat-label">Total Reviews</span>
                    </div>
                </div>
            </div>

            <!-- Review Form -->
            <!-- Review Form Section - FIXED ACTION URL -->
<?php if ($user_id && $customerId): ?>
    <?php if ($hasPurchased): ?>
    <div class="review-form-section">
        <h3 class="form-title"><?php echo $userReview ? 'Update Your Review' : 'Write a Review'; ?></h3>
        <!-- FIX: Correct file path is /customer/product.php -->
        <form method="POST" action="<?php echo $baseUrl; ?>/customer/product.php?id=<?php echo (int)$productId; ?>" class="review-form" id="reviewForm" novalidate>
            <div class="form-group">
                <label for="rating" class="form-label">Your Rating <span class="required">*</span></label>
                <div class="star-rating-input">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <input type="radio" name="rating" id="star<?php echo $i; ?>" value="<?php echo $i; ?>" 
                           <?php echo ($userReview && (int)$userReview['rating'] === $i) ? 'checked' : ($i === 5 && !$userReview ? 'checked' : ''); ?>>
                    <label for="star<?php echo $i; ?>">
                        <svg width="24" height="24" viewBox="0 0 24 24" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="review_text" class="form-label">Your Review <span class="required">*</span></label>
                <textarea id="review_text" name="review_text" class="form-textarea" 
                          placeholder="Share your experience with this product..." 
                          rows="5"><?php echo $userReview ? htmlspecialchars($userReview['review_text']) : ''; ?></textarea>
                <span class="form-hint">Minimum 10 characters</span>
                <span class="form-error" id="review-error"></span>
            </div>

            <div class="form-actions">
                <button class="btn btn-primary" type="submit" name="review_submit">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <?php echo $userReview ? 'Update Review' : 'Submit Review'; ?>
                </button>
                <?php if ($userReview): ?>
                <button type="submit" name="review_delete" class="btn btn-danger" 
                        onclick="return confirm('Are you sure you want to delete your review? This action cannot be undone.');">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="3,6 5,6 21,6"/>
                        <path d="m19,6v14a2,2 0 0,1 -2,2H7a2,2 0 0,1 -2,-2V6m3,0V4a2,2 0 0,1 2,-2h4a2,2 0 0,1 2,2v2"/>
                        <line x1="10" y1="11" x2="10" y2="17"/>
                        <line x1="14" y1="11" x2="14" y2="17"/>
                    </svg>
                    Delete Review
                </button>
                <?php endif; ?>
            </div>
        </form>
    </div>
    <?php else: ?>
    <div class="purchase-required">
        <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
            <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/>
            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
        </svg>
        <p>You can only review products you've purchased and received.</p>
        <p class="hint">Complete a purchase to share your experience!</p>
    </div>
    <?php endif; ?>
<?php else: ?>
<div class="login-prompt">
    <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>
    </svg>
    <p>Please <a href="<?php echo $baseUrl; ?>/user/login.php">sign in</a> to write a review</p>
</div>
<?php endif; ?>

            <!-- Reviews List -->
            <div class="reviews-list">
                <?php if (empty($reviews)): ?>
                <div class="no-reviews">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                    </svg>
                    <h3>No Reviews Yet</h3>
                    <p>Be the first to review this product</p>
                </div>
                <?php else: ?>
                <?php foreach ($reviews as $rv): 
                    $reviewerName = $rv['fullname'] ?? 'Customer';
                    $reviewText = mask_bad_words($rv['review_text']);
                ?>
                <div class="review-item">
                    <div class="review-header-row">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">
                                <?php echo strtoupper(substr($reviewerName, 0, 1)); ?>
                            </div>
                            <div>
                                <div class="reviewer-name"><?php echo htmlspecialchars($reviewerName); ?></div>
                                <div class="review-date"><?php echo date('F j, Y', strtotime($rv['created_at'])); ?></div>
                            </div>
                        </div>
                        <div class="review-rating">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <svg width="14" height="14" viewBox="0 0 24 24" 
                                     fill="<?php echo $i <= (int)$rv['rating'] ? '#0a0a0a' : 'none'; ?>" 
                                     stroke="#0a0a0a" stroke-width="2">
                                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                                </svg>
                            <?php endfor; ?>
                            <span class="rating-number"><?php echo (int)$rv['rating']; ?>.0</span>
                        </div>
                    </div>
                    <?php if (!empty($reviewText)): ?>
                    <div class="review-content">
                        <?php echo nl2br(htmlspecialchars($reviewText)); ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </div>
</main>

<script src="<?php echo $baseUrl; ?>/customer/js/product.js"></script>

<?php include __DIR__ . '/../includes/footer.php'; ?>