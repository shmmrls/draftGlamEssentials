<?php
require_once('../../includes/config.php');
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Redirect to login if not authorized
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','asst_admin','staff'], true)) {
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$pageCss = $baseUrl . '/admin/categories/css/categories.css';
include '../../includes/adminHeader.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="categories-page">
    <div class="categories-container">
        <?php include '../../includes/alert.php'; ?>

        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Manage Categories</h1>
                    <p class="page-subtitle">Organize your product catalog</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo $baseUrl; ?>/admin/categories/create.php" class="btn-primary">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                        </svg>
                        Add New Category
                    </a>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-section">
            <div class="search-box">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                </svg>
                <input type="text" id="searchInput" placeholder="Search categories..." class="search-input">
            </div>

            <div class="filter-controls">
                <div class="filter-group">
                    <label for="sortFilter">Sort By</label>
                    <select id="sortFilter" class="filter-select">
                        <option value="latest">Latest</option>
                        <option value="oldest">Oldest</option>
                        <option value="name-asc">Name: A to Z</option>
                        <option value="name-desc">Name: Z to A</option>
                    </select>
                </div>

                <button id="resetFilters" class="btn-reset">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="1 4 1 10 7 10"/><polyline points="23 20 23 14 17 14"/>
                        <path d="M20.49 9A9 9 0 0 0 5.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 0 1 3.51 15"/>
                    </svg>
                    Reset
                </button>
            </div>
        </div>

        <?php
        // Fetch categories with product count
        $sql = "SELECT c.category_id, c.category_name, c.img_name, 
                       COUNT(p.product_id) AS product_count
                FROM categories c
                LEFT JOIN products p ON c.category_id = p.category_id
                GROUP BY c.category_id, c.category_name, c.img_name
                ORDER BY c.category_id DESC";
        $result = mysqli_query($conn, $sql);
        ?>

        <!-- Categories Grid Section -->
        <div class="categories-grid">
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                    // Handle image display
                    $imgTag = '<div class="category-img-placeholder"><svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div>';
                    $imgBase = trim($row['img_name'] ?? '');
                    if ($imgBase !== '') {
                        $dir = __DIR__ . '/../../item/product_category/';
                        $webBase = $baseUrl . '/item/product_category/';
                        $exts = ['.jpg','.png','.webp','.jpeg'];
                        foreach ($exts as $ext) {
                            $fs = $dir . $imgBase . $ext;
                            if (file_exists($fs)) { 
                                $imgTag = '<img src="'.$webBase.$imgBase.$ext.'" alt="'.htmlspecialchars($row['category_name']).'" class="category-img" />'; 
                                break; 
                            }
                        }
                    }
                    ?>
                    <div class="category-card" 
                         data-name="<?= htmlspecialchars($row['category_name']) ?>"
                         data-id="<?= (int)$row['category_id'] ?>">
                        <div class="category-image">
                            <?= $imgTag ?>
                        </div>
                        <div class="category-content">
                            <div class="category-header">
                                <h3 class="category-name"><?= htmlspecialchars($row['category_name']) ?></h3>
                                <span class="category-id">#<?= (int)$row['category_id'] ?></span>
                            </div>
                            <div class="category-stats">
                                <span class="product-count">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/>
                                    </svg>
                                    <?= (int)$row['product_count'] ?> Products
                                </span>
                            </div>
                            <div class="category-actions">
                                <a href="<?= $baseUrl ?>/admin/categories/edit.php?id=<?= (int)$row['category_id'] ?>" class="btn-action btn-edit">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Edit
                                </a>
                                <a href="<?= $baseUrl ?>/admin/categories/delete.php?id=<?= (int)$row['category_id'] ?>" 
                                   onclick="return confirm('Are you sure you want to delete this category? All products in this category will also be deleted.');" 
                                   class="btn-action btn-delete">
                                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/>
                                        <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                    </svg>
                    <p>No categories found</p>
                    <a href="<?php echo $baseUrl; ?>/admin/categories/create.php" class="btn-secondary">Add Your First Category</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- No Results Message -->
        <div id="noResults" class="no-results" style="display: none;">
            <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
            </svg>
            <p>No categories match your search</p>
        </div>
    </div>
</main>

<script src="<?php echo $baseUrl; ?>/admin/categories/js/categories.js"></script>

<?php require_once('../../includes/footer.php'); ?>