<?php
require_once __DIR__ . '/../includes/config.php';
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}

// Redirect to login if not authorized
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','asst_admin','staff'], true)) {
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$pageCss = $baseUrl . '/includes/style/admin.css';
include __DIR__ . '/../includes/header.php';
?>

<main class="container" style="max-width:1200px;margin:40px auto;padding:0 16px;">
    <?php include __DIR__ . '/../includes/alert.php'; ?>

    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h1 style="margin:0;">Manage Items</h1>
        <a href="{$baseUrl}/item/create.php" style="padding:10px 14px;background:#7a1530;color:#fff;text-decoration:none;border-radius:6px;">+ Add Item</a>
    </div>

    <?php
    // Fetch products with categories, inventory qty, and main image name
    $sql = "SELECT p.product_id, p.product_name, p.price, p.is_available, p.is_featured, p.main_img_name, c.category_name, 
                   COALESCE(i.quantity, 0) AS quantity, COALESCE(i.unit, 'pcs') AS unit
            FROM products p
            JOIN categories c ON c.category_id = p.category_id
            LEFT JOIN inventory i ON i.product_id = p.product_id
            ORDER BY p.product_id DESC";
    $result = mysqli_query($conn, $sql);
    ?>

    <div style="overflow-x:auto;background:#fff;border:1px solid #eee;border-radius:8px;">
        <table style="width:100%;border-collapse:collapse;">
            <thead>
                <tr style="background:#f7f7f7;text-align:left;">
                    <th style="padding:12px;border-bottom:1px solid #eee;">Image</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">ID</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Name</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Category</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Price</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Qty</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Featured</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Available</th>
                    <th style="padding:12px;border-bottom:1px solid #eee;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                    <?php
                      $imgTag = '<div style="width:48px;height:48px;border:1px solid #eee;border-radius:6px;background:#fafafa"></div>';
                      $imgBase = trim($row['main_img_name'] ?? '');
                      if ($imgBase !== '') {
                        $dir = __DIR__ . '/products/';
                        $webBase = '/GlamEssentials/item/products/';
                        $exts = ['.jpg','.png','.webp','.jpeg'];
                        foreach ($exts as $ext) {
                          $fs = $dir . $imgBase . $ext;
                          if (file_exists($fs)) { $imgTag = '<img src="'.$webBase.$imgBase.$ext.'" alt="thumb" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid #eee" />'; break; }
                        }
                      }
                    ?>
                    <tr>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;vertical-align:middle;"><?= $imgTag ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;vertical-align:middle;"><?= (int)$row['product_id'] ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($row['product_name']) ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;"><?= htmlspecialchars($row['category_name']) ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;">₱<?= number_format((float)$row['price'], 2) ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;"><?= (int)$row['quantity'] . ' ' . htmlspecialchars($row['unit']) ?></td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;">
                            <?= $row['is_featured'] ? 'Yes' : 'No' ?>
                        </td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;">
                            <?= $row['is_available'] ? 'Yes' : 'No' ?>
                        </td>
                        <td style="padding:10px;border-bottom:1px solid #f0f0f0;white-space:nowrap;">
                            <a href="<?= $baseUrl ?>/item/edit.php?id=<?= (int)$row['product_id'] ?>" style="margin-right:8px;">Edit</a>
                        <a href="<?= $baseUrl ?>/item/delete.php?id=<?= (int)$row['product_id'] ?>" onclick="return confirm('Delete this item?');" style="color:#b00020;">Delete</a>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" style="padding:14px;text-align:center;color:#666;">No items found.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../includes/footer.php'; ?>
