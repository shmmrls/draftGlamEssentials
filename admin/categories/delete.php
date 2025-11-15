<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start(); 
}
require_once('../../includes/config.php');

// Redirect to login if not authorized
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','asst_admin','staff'], true)) {
    header('Location: ' . $baseUrl . '/user/login.php');
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    $_SESSION['error'] = 'Invalid category.';
    header('Location: index.php');
    exit;
}

mysqli_begin_transaction($conn);
try {
    // Get image name before deleting
    $imgQuery = mysqli_prepare($conn, "SELECT img_name FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($imgQuery, 'i', $id);
    mysqli_stmt_execute($imgQuery);
    $imgResult = mysqli_stmt_get_result($imgQuery);
    $imgData = mysqli_fetch_assoc($imgResult);
    mysqli_stmt_close($imgQuery);

    // Delete category (products will cascade due to FK)
    $stmt = mysqli_prepare($conn, "DELETE FROM categories WHERE category_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) { 
        throw new Exception('Failed to delete category.'); 
    }
    if (mysqli_stmt_affected_rows($stmt) === 0) { 
        throw new Exception('Category not found or already deleted.'); 
    }
    mysqli_stmt_close($stmt);

    // Delete image file if exists
    if ($imgData && !empty($imgData['img_name'])) {
        $imgBase = $imgData['img_name'];
        $categoryImagesDir = __DIR__ . '/../../item/product_category';
        foreach (['.jpg','.png','.webp','.jpeg'] as $ext) {
            $imgPath = $categoryImagesDir . '/' . $imgBase . $ext;
            if (file_exists($imgPath)) {
                @unlink($imgPath);
                break;
            }
        }
    }

    mysqli_commit($conn);
    $_SESSION['success'] = 'Category deleted successfully.';
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}