<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin','asst_admin','staff'], true)) {
    $_SESSION['error'] = 'Unauthorized.';
    header('Location: ../user/login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid item.';
    header('Location: index.php');
    exit;
}

$prodSql = "SELECT * FROM products WHERE product_id = $id";
$prodRes = mysqli_query($conn, $prodSql);
$product = $prodRes ? mysqli_fetch_assoc($prodRes) : null;

$invSql = "SELECT * FROM inventory WHERE product_id = $id";
$invRes = mysqli_query($conn, $invSql);
$inventory = $invRes ? mysqli_fetch_assoc($invRes) : null;

if (!$product) {
    $_SESSION['error'] = 'Item not found.';
    header('Location: index.php');
    exit;
}

$cats = mysqli_query($conn, "SELECT category_id, category_name FROM categories ORDER BY category_name");

$pageCss = '';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="max-width:800px;margin:40px auto;padding:0 16px;">
    <?php include __DIR__ . '/../includes/alert.php'; ?>
    <h1 style="margin:0 0 16px 0;">Edit Item #<?= (int)$id ?></h1>

    <form action="update.php?id=<?= (int)$id ?>" method="post" enctype="multipart/form-data" style="display:grid;gap:12px;background:#fff;border:1px solid #eee;border-radius:8px;padding:16px;">
        <label>
            <div>Category</div>
            <select name="category_id" required style="width:100%;padding:8px;">
                <option value="">Select category</option>
                <?php while ($c = mysqli_fetch_assoc($cats)): ?>
                    <option value="<?= (int)$c['category_id'] ?>" <?= ((int)$c['category_id'] === (int)$product['category_id']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['category_name']) ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </label>

        <label>
            <div>Product Name</div>
            <input type="text" name="product_name" value="<?= htmlspecialchars($product['product_name']) ?>" required style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Description</div>
            <textarea name="description" rows="4" style="width:100%;padding:8px;"><?= htmlspecialchars($product['description'] ?? '') ?></textarea>
        </label>

        <label>
            <div>Price</div>
            <input type="number" name="price" step="0.01" min="0" value="<?= htmlspecialchars($product['price']) ?>" required style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Main Image Name</div>
            <input type="text" name="main_img_name" value="<?= htmlspecialchars($product['main_img_name'] ?? '') ?>" style="width:100%;padding:8px;" />
        </label>

        <label>
            <div>Upload New Main Image</div>
            <input type="file" name="main_image" accept="image/*" style="width:100%;padding:8px;" />
        </label>

        <div style="display:flex;gap:16px;">
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_featured" value="1" <?= !empty($product['is_featured']) ? 'checked' : '' ?> /> Featured
            </label>
            <label style="display:flex;align-items:center;gap:8px;">
                <input type="checkbox" name="is_available" value="1" <?= !empty($product['is_available']) ? 'checked' : '' ?> /> Available
            </label>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px;">
            <label>
                <div>Quantity</div>
                <input type="number" name="quantity" min="0" value="<?= htmlspecialchars($inventory['quantity'] ?? 0) ?>" required style="width:100%;padding:8px;" />
            </label>
            <label>
                <div>Unit</div>
                <input type="text" name="unit" value="<?= htmlspecialchars($inventory['unit'] ?? 'pcs') ?>" style="width:100%;padding:8px;" />
            </label>
            <label>
                <div>Reorder Level</div>
                <input type="number" name="reorder_level" min="0" value="<?= htmlspecialchars($inventory['reorder_level'] ?? 10) ?>" style="width:100%;padding:8px;" />
            </label>
        </div>

        <?php
        $imgs = [];
        $q = mysqli_prepare($conn, 'SELECT image_id, img_name FROM product_images WHERE product_id = ? ORDER BY image_id DESC');
        mysqli_stmt_bind_param($q, 'i', $id);
        mysqli_stmt_execute($q);
        $r = mysqli_stmt_get_result($q);
        if ($r) { $imgs = mysqli_fetch_all($r, MYSQLI_ASSOC); }
        mysqli_stmt_close($q);
        ?>

        <div>
            <div>Gallery Images</div>
            <?php if ($imgs): ?>
                <div style="display:flex;flex-wrap:wrap;gap:10px;margin:8px 0;">
                    <?php foreach ($imgs as $gi):
                        $full = null;
                        $base = $gi['img_name'];
                        foreach (['.jpg','.png','.webp','.jpeg'] as $ext) {
                            $path = 'product_images/' . $base . $ext;
                            if (file_exists(__DIR__ . '/' . $path)) { $full = $path; break; }
                        }
                    ?>
                        <div style="border:1px solid #eee;border-radius:8px;padding:8px;display:flex;flex-direction:column;align-items:center;width:150px;">
                            <?php if ($full): ?><img src="<?= htmlspecialchars($full) ?>" style="max-width:100%;max-height:100px;object-fit:cover;border-radius:6px;" alt="img"><?php else: ?><div style="height:100px;display:flex;align-items:center;justify-content:center;color:#666;">No file</div><?php endif; ?>
                            <a href="gallery_delete.php?pid=<?= (int)$id ?>&img_id=<?= (int)$gi['image_id'] ?>" onclick="return confirm('Delete this image?');" style="margin-top:6px;color:#b00020;">Delete</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="color:#666;margin:6px 0;">No gallery images.</div>
            <?php endif; ?>
            <label>
                <div>Add Gallery Images</div>
                <input type="file" name="gallery_images[]" accept="image/*" multiple style="width:100%;padding:8px;" />
            </label>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" style="padding:10px 14px;background:#7a1530;color:#fff;border:none;border-radius:6px;">Update</button>
            <a href="index.php" style="padding:10px 14px;background:#eee;color:#333;text-decoration:none;border-radius:6px;">Cancel</a>
        </div>
    </form>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
