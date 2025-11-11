<?php
require_once __DIR__ . '/includes/config.php';
$pageCss = 'product-list.css';
include __DIR__ . '/includes/header.php';

// Fetch all active products with their categories
$sql = "SELECT p.*, c.category_name 
        FROM products p 
        JOIN categories c ON p.category_id = c.category_id 
        WHERE p.is_available = 1
        ORDER BY p.product_name";
$result = mysqli_query($conn, $sql);
?>

<main class="container" style="max-width: 1200px; margin: 40px auto; padding: 0 16px;">
    <h1>Our Products</h1>
    
    <div class="product-grid">
        <?php if ($result && mysqli_num_rows($result) > 0): ?>
            <?php while ($product = mysqli_fetch_assoc($result)): 
                // Get product image
                $imgPath = '/assets/default.png';
                if (!empty($product['main_img_name'])) {
                    $imgBase = $product['main_img_name'];
                    $extensions = ['.jpg', '.png', '.webp'];
                    foreach ($extensions as $ext) {
                        $fullPath = __DIR__ . '/item/products/' . $imgBase . $ext;
                        if (file_exists($fullPath)) {
                            $imgPath = '/item/products/' . $imgBase . $ext;
                            break;
                        }
                    }
                }
            ?>
                <div class="product-card">
                    <a href="<?php echo $baseUrl; ?>/products.php?id=<?php echo $product['product_id']; ?>">
                        <img src="<?php echo $baseUrl . $imgPath; ?>" alt="<?php echo htmlspecialchars($product['product_name']); ?>">
                        <h3><?php echo htmlspecialchars($product['product_name']); ?></h3>
                        <p class="price">₱<?php echo number_format($product['price'], 2); ?></p>
                        <p class="category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                    </a>
                </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p>No products found.</p>
        <?php endif; ?>
    </div>
</main>

<style>
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    margin-top: 30px;
}

.product-card {
    border: 1px solid #eee;
    border-radius: 8px;
    overflow: hidden;
    transition: transform 0.2s;
}

.product-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.product-card img {
    width: 100%;
    height: 200px;
    object-fit: cover;
}

.product-card h3 {
    margin: 10px 15px;
    font-size: 1.1rem;
}

.product-card .price {
    margin: 0 15px 10px;
    font-weight: bold;
    color: #7a1530;
}

.product-card .category {
    margin: 0 15px 15px;
    color: #666;
    font-size: 0.9rem;
}
</style>

<?php include __DIR__ . '/includes/footer.php'; ?>