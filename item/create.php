<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','asst_admin','staff'], true)) {
    header('Location: ../user/login.php');
    exit;
}
$pageCss = 'admin.css';
include __DIR__ . '/../includes/header.php';

$cats = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");
?>

<main class="container" style="max-width:800px;margin:40px auto;padding:0 16px;">
    <?php include __DIR__ . '/../includes/alert.php'; ?>
    <h1 style="margin:0 0 16px 0;">Add Item</h1>

    <form action="store.php" method="post" enctype="multipart/form-data" style="display:grid;gap:12px;background:#fff;border:1px solid #eee;border-radius:8px;padding:16px;">
        <label>
            <div>Category</div>
            <select name="category_id" required style="width:100%;padding:8px;">
                <option value="">Select category</option>
                <?php while ($c = mysqli_fetch_assoc($cats)): ?>
                    <option value="<?= (int)$c['category_id'] ?>"><?= htmlspecialchars($c['category_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </label>

        <label>
            <div>Product Name</div>
            <input type="text" name="product_name" required style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Description</div>
            <textarea name="description" rows="4" style="width:100%;padding:8px;"></textarea>
        </label>

        <label>
            <div>Price</div>
            <input type="number" name="price" step="0.01" min="0" required style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Main Image Name</div>
            <input type="text" name="main_img_name" placeholder="e.g. keratin_treatment" style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Upload Main Image</div>
            <input type="file" name="main_image" accept="image/*" style="width:100%;padding:8px;" />
        </label>

        <div style="display:flex;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_featured" value="1" /> Featured
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_available" value="1" checked /> Available
            </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <label>
                <div>Quantity</div>
                <input type="number" name="quantity" min="0" value="0" required style="width:100%;padding:8px;" />
            </label>
            <label>
                <div>Unit</div>
                <input type="text" name="unit" value="pcs" style="width:100%;padding:8px;" />
            </label>
            <label>
                <div>Reorder Level</div>
                <input type="number" name="reorder_level" min="0" value="10" style="width:100%;padding:8px;" />
            </label>
        </div>

        <label>
            <div>Upload Gallery Images</div>
            <input type="file" name="gallery_images[]" accept="image/*" multiple style="width:100%;padding:8px;" />
        </label>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn btn-primary">Save</button>
            <a href="index.php" class="btn btn-light">Cancel</a>
        </div>
    </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
