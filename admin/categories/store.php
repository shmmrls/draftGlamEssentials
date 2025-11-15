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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error'] = 'Invalid request.';
    header('Location: index.php');
    exit;
}

$category_name = trim($_POST['category_name'] ?? '');
$img_name = trim($_POST['img_name'] ?? '');

// Validation
$errors = [];

if ($category_name === '') {
    $errors[] = 'Category name is required.';
}

if (!empty($img_name) && !preg_match('/^[a-z0-9_\-]+$/i', $img_name)) {
    $errors[] = 'Image name can only contain letters, numbers, underscores, and hyphens.';
}

if (!empty($errors)) {
    $_SESSION['error'] = implode('<br>', $errors);
    header('Location: create.php');
    exit;
}

// Prepare upload helpers
$allowedExts = ['jpg','jpeg','png','webp'];
$maxSize = 5 * 1024 * 1024; // 5MB
$categoryImagesDir = __DIR__ . '/../../item/product_category';
if (!is_dir($categoryImagesDir)) { @mkdir($categoryImagesDir, 0777, true); }

// Handle category image upload if provided
if (!empty($_FILES['category_image']['name']) && is_uploaded_file($_FILES['category_image']['tmp_name'])) {
    $f = $_FILES['category_image'];
    if ($f['error'] === UPLOAD_ERR_OK && $f['size'] <= $maxSize) {
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowedExts, true)) {
            $base = $img_name !== '' ? preg_replace('/[^a-z0-9_\-]/i','_', $img_name) : (uniqid('cat_', true));
            $dest = $categoryImagesDir . '/' . $base . '.' . $ext;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $img_name = $base; // save basename only
            } else {
                $_SESSION['error'] = 'Failed to upload image.';
                header('Location: create.php');
                exit;
            }
        } else {
            $_SESSION['error'] = 'Invalid image format. Only JPG, PNG, and WEBP are allowed.';
            header('Location: create.php');
            exit;
        }
    } else {
        $_SESSION['error'] = 'Image upload error. File may be too large (max 5MB).';
        header('Location: create.php');
        exit;
    }
}

mysqli_begin_transaction($conn);
try {
    $stmt = mysqli_prepare($conn, "INSERT INTO categories (category_name, img_name) VALUES (?, ?)");
    mysqli_stmt_bind_param($stmt, 'ss', $category_name, $img_name);
    if (!mysqli_stmt_execute($stmt)) { 
        throw new Exception('Failed to save category.'); 
    }
    mysqli_stmt_close($stmt);

    mysqli_commit($conn);
    $_SESSION['success'] = 'Category created successfully.';
    header('Location: index.php');
    exit;
} catch (Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = 'Error: ' . $e->getMessage();
    header('Location: create.php');
    exit;
}