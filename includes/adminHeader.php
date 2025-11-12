<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration
require_once __DIR__ . '/config.php';

if (!isset($baseUrl)) {
    $docRoot = rtrim(str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])), '/');
    $projectRoot = rtrim(str_replace('\\', '/', realpath(__DIR__ . '/..')), '/');
    $baseUrl = str_replace($docRoot, '', $projectRoot);
    if ($baseUrl === '') $baseUrl = '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GlamEssentials - Admin Panel</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/style.css">
    <?php if (!empty($pageCss)): ?>
        <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/includes/style/<?= htmlspecialchars($pageCss) ?>">
    <?php endif; ?>
    <script src="<?= htmlspecialchars($baseUrl) ?>/script.js" defer></script>
</head>
<body>

<!--    🌸 Top Banner -->
<div class="top-banner">
    <div class="banner-content">
        <span class="banner-text">ADMIN DASHBOARD</span>
        <span class="banner-text">MANAGE YOUR SALON BUSINESS</span>
        <span class="banner-text">ADMIN DASHBOARD</span>
        <span class="banner-text">MANAGE YOUR SALON BUSINESS</span>
    </div>
</div>

<!-- 🖤 Header -->
<header class="header-container">
    <div class="header-main">
        <!-- Logo -->
        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/dashboard.php" class="logo">
            <img src="<?= htmlspecialchars($baseUrl) ?>/assets/logo1.png" alt="GlamEssentials" class="logo-img">
        </a>

        <!-- Navigation -->
        <nav class="nav-container" id="mobile-nav">
            <ul class="main-nav">
                <!-- Dashboard Link -->
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/admin/dashboard.php" class="nav-link">Dashboard</a></li>
                
                <!-- Admin Management Dropdown -->
                <li class="nav-item dropdown">
                    <a href="#" class="nav-link">Manage</a>
                    <div class="dropdown-menu">
                        <a href="<?= htmlspecialchars($baseUrl) ?>/item/index.php">Products</a>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/categories/ind_categories.php">Categories</a>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/users/ind_users.php">Users</a>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/orders.php">Orders</a>
                        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/reviews.php">Reviews</a>
                    </div>
                </li>
            </ul>
        </nav>

        <!-- Header Actions (Desktop Only) -->
        <div class="header-actions desktop-only">
            <?php 
            // Fetch admin user data for dropdown
            if (isset($_SESSION['user_id'])) {
                $header_user_id = (int) $_SESSION['user_id'];
                $header_stmt = $conn->prepare("SELECT name, img_name FROM users WHERE user_id = ?");
                $header_stmt->bind_param("i", $header_user_id);
                $header_stmt->execute();
                $header_result = $header_stmt->get_result();
                $header_user = $header_result->fetch_assoc();
                $header_stmt->close();

                // Set defaults safely
                if (empty($header_user['img_name'])) {
                    $header_user['img_name'] = 'nopfp.jpg';
                }

                $profile_pic = htmlspecialchars($baseUrl) . '/user/images/profile_pictures/' . htmlspecialchars($header_user['img_name']);
                $user_name = htmlspecialchars($header_user['name'] ?? 'Admin');
            ?>

            <!-- Account Dropdown -->
            <div class="account-dropdown-wrapper">
                <button class="icon-btn account-dropdown-btn" aria-label="Account" type="button">
                    <img src="<?php echo $profile_pic; ?>" 
                         alt="Profile" 
                         class="account-profile-img"
                         onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';">
                </button>
                <div class="account-dropdown-menu">
                    <div class="account-dropdown-header">
                        <img src="<?php echo $profile_pic; ?>" 
                             alt="Profile" 
                             class="account-dropdown-avatar"
                             onerror="this.src='<?php echo htmlspecialchars($baseUrl); ?>/user/images/profile_pictures/nopfp.jpg';">
                        <div class="account-dropdown-name"><?php echo $user_name; ?></div>
                        <div class="account-dropdown-role">Administrator</div>
                    </div>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/profile.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Profile</span>
                    </a>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/index.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
                            <polyline points="9 22 9 12 15 12 15 22"/>
                        </svg>
                        <span>View Store</span>
                    </a>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/logout.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <polyline points="16 17 21 12 16 7"/>
                            <line x1="21" y1="12" x2="9" y2="12"/>
                        </svg>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
            <?php } ?>
        </div>

        <!-- Mobile Menu Button -->
        <button class="hamburger-btn" aria-label="Menu">
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
            <span class="hamburger-bar"></span>
        </button>
    </div>
</header>

<style>
/* Additional styles for admin role badge */
.account-dropdown-role {
    font-size: 11px;
    color: rgba(0, 0, 0, 0.5);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 4px;
}
</style>