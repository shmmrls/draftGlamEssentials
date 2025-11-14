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
    <title>GlamEssentials - Professional Salon Supplies</title>
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
        <span class="banner-text">WELCOME BACK TO GLAMESSENTIALS</span>
            <span class="banner-text">ENJOY YOUR EXCLUSIVE DEALS TODAY</span>
            <span class="banner-text">THANK YOU FOR BEING PART OF OUR COMMUNITY</span>
            <span class="banner-text">SHOP YOUR FAVORITE PRODUCTS NOW</span>
    </div>
</div>

<!-- 🖤 Header -->
<header class="header-container">
    <div class="header-main">
        <!-- Logo -->
        <a href="<?= htmlspecialchars($baseUrl) ?>/customer/index.php" class="logo">
            <img src="<?= htmlspecialchars($baseUrl) ?>/assets/logo1.png" alt="GlamEssentials" class="logo-img">
        </a>

        <!-- Navigation -->
        <nav class="nav-container" id="mobile-nav">
            <ul class="main-nav">
                <!-- Common links for everyone -->
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/index.php" class="nav-link">Home</a></li>
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/product-list.php" class="nav-link">Products</a></li>
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/about.php" class="nav-link">About</a></li>
                <li><a href="<?= htmlspecialchars($baseUrl) ?>/customer/faq.php" class="nav-link">FAQ</a></li>

                <?php if (isset($_SESSION['user_id']) && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'customer'): ?>
                    <!-- 👤 CUSTOMER ACCOUNT NAV -->
                    <li class="nav-item dropdown">
                        <a href="#" class="nav-link">Account</a>
                        <div class="dropdown-menu">
                            <a href="<?= htmlspecialchars($baseUrl) ?>/user/profile.php">Profile</a>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/user/myorders.php">My Orders</a>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/customer/cart/view_cart.php">My Cart</a>
                            <a href="<?= htmlspecialchars($baseUrl) ?>/user/myreviews.php">My Reviews</a>
                        </div>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>

        <!-- Header Actions (Desktop Only) -->
        <div class="header-actions desktop-only">
            <!-- Search Icon -->
            <a href="<?= htmlspecialchars($baseUrl) ?>/search.php" class="icon-btn" aria-label="Search">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" 
                        d="M21 21l-5.197-5.197m0 0A7.5 7.5 0 
                        105.196 5.196a7.5 7.5 0 
                        0010.607 10.607z" />
                </svg>
            </a>

            <!-- Cart Icon -->
            <a href="<?= htmlspecialchars($baseUrl) ?>/customer/cart/view_cart.php" class="icon-btn" aria-label="Shopping Cart">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" 
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" 
                        d="M15.75 10.5V6a3.75 3.75 0 
                        10-7.5 0v4.5m11.356-1.993l1.263 
                        12c.07.665-.45 1.243-1.119 
                        1.243H4.25a1.125 1.125 0 
                        01-1.12-1.243l1.264-12A1.125 
                        1.125 0 015.513 
                        7.5h12.974c.576 0 1.059.435 
                        1.119 1.007zM8.625 
                        10.5a.375.375 0 
                        11-.75 0 .375.375 0 
                        01.75 0zm7.5 
                        0a.375.375 0 11-.75 0 .375.375 0 
                        01.75 0z" />
                </svg>
            </a>

            <!-- Account Icon / Dropdown -->
            <?php if (isset($_SESSION['user_id'])): 
                // Fetch user data for dropdown
                $header_user_id = (int) $_SESSION['user_id'];
                $header_stmt = $conn->prepare("SELECT name, img_name, role FROM users WHERE user_id = ?");
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
                $user_name = htmlspecialchars($header_user['name'] ?? 'User');
                $user_role = ucfirst($header_user['role'] ?? 'customer');
            ?>

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
                        <div class="account-dropdown-role"><?php echo $user_role; ?></div>
                    </div>
                    <div class="account-dropdown-divider"></div>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/profile.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                            <circle cx="12" cy="7" r="4"/>
                        </svg>
                        <span>Profile</span>
                    </a>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/myorders.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/>
                            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"/>
                        </svg>
                        <span>My Orders</span>
                    </a>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/cart/view.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="9" cy="21" r="1"/>
                            <circle cx="20" cy="21" r="1"/>
                            <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                        </svg>
                        <span>My Cart</span>
                    </a>
                    <a href="<?= htmlspecialchars($baseUrl) ?>/user/myreviews.php" class="account-dropdown-item">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/>
                        </svg>
                        <span>My Reviews</span>
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
            <?php else: ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>/user/login.php" class="icon-btn" aria-label="Account">
                <svg width="24" height="24" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1">
                    <circle cx="10" cy="7" r="3.5"/>
                    <path d="M4 18c0-3.5 2.5-6 6-6s6 2.5 6 6"/>
                </svg>
            </a>
            <?php endif; ?>
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
/* Additional styles for user role badge */
.account-dropdown-role {
    font-size: 11px;
    color: rgba(0, 0, 0, 0.5);
    letter-spacing: 1px;
    text-transform: uppercase;
    margin-top: 4px;
}
</style>