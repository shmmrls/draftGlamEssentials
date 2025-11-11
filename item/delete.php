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

mysqli_begin_transaction($conn);
try {
    // Delete product (inventory rows will cascade due to FK)
    $stmt = mysqli_prepare($conn, "DELETE FROM products WHERE product_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    if (!mysqli_stmt_execute($stmt)) { throw new Exception('Failed to delete product.'); }
    if (mysqli_stmt_affected_rows($stmt) === 0) { throw new Exception('Item not found or already deleted.'); }
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    $_SESSION['success'] = 'Item deleted successfully.';
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: index.php');
    exit;
}
