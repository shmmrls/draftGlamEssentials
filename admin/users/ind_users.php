<?php
ob_start();
session_start();
require_once('../../includes/config.php');

// Check if user is logged in and is admin/staff
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to access the user management page.";
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user role
$user_stmt = $conn->prepare("SELECT role, name, email, img_name FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Check if user has admin or staff role
if (!in_array($user_data['role'], ['admin', 'staff'])) {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: ../../index.php");
    exit;
}

// Set default profile image
// if (empty($user_data['img_name'])) {
//     $user_data['img_name'] = 'nopfp.jpg';
// }

if (!empty($user_data['img_name'])) {
	$profile_pic = htmlspecialchars($baseUrl) . '/user/images/profile_pictures/' . htmlspecialchars($user_data['img_name']);
	} else {
	$profile_pic = htmlspecialchars($baseUrl) . '/user/images/profile_pictures/nopfp.jpg';
	}
                    

// Handle AJAX requests for deactivating/activating users
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'toggle_active') {
        $target_user_id = (int) $_POST['user_id'];
        $new_status = (int) $_POST['is_active'];
        
        // Prevent deactivating self
        if ($target_user_id === $user_id) {
            echo json_encode(['success' => false, 'message' => 'Cannot deactivate your own account.']);
            exit;
        }
        
        $toggle_stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE user_id = ?");
        $toggle_stmt->bind_param("ii", $new_status, $target_user_id);
        
        if ($toggle_stmt->execute()) {
            $status_text = $new_status ? 'activated' : 'deactivated';
            echo json_encode(['success' => true, 'message' => "User successfully {$status_text}."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user status.']);
        }
        $toggle_stmt->close();
        exit;
    }
    
    if ($_POST['action'] === 'update_role') {
        $target_user_id = (int) $_POST['user_id'];
        $new_role = $_POST['role'];
        
        // Validate role
        if (!in_array($new_role, ['customer', 'staff', 'admin'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
            exit;
        }
        
        // Prevent changing own role
        if ($target_user_id === $user_id) {
            echo json_encode(['success' => false, 'message' => 'Cannot change your own role.']);
            exit;
        }
        
        // Only admins can assign admin role
        if ($new_role === 'admin' && $user_data['role'] !== 'admin') {
            echo json_encode(['success' => false, 'message' => 'Only admins can assign admin role.']);
            exit;
        }
        
        $role_stmt = $conn->prepare("UPDATE users SET role = ? WHERE user_id = ?");
        $role_stmt->bind_param("si", $new_role, $target_user_id);
        
        if ($role_stmt->execute()) {
            echo json_encode(['success' => true, 'message' => "User role updated to {$new_role}."]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update user role.']);
        }
        $role_stmt->close();
        exit;
    }
}

// Pagination settings
$records_per_page = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($page - 1) * $records_per_page;

// Filter settings
$role_filter = isset($_GET['role']) && in_array($_GET['role'], ['customer', 'staff', 'admin']) ? $_GET['role'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search_query = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build WHERE clause
$where_conditions = [];
$params = [];
$types = '';

if ($role_filter) {
    $where_conditions[] = "role = ?";
    $params[] = $role_filter;
    $types .= 's';
}

if ($status_filter !== '') {
    $where_conditions[] = "is_active = ?";
    $params[] = (int)$status_filter;
    $types .= 'i';
}

if ($search_query) {
    $where_conditions[] = "(name LIKE ? OR email LIKE ?)";
    $search_param = "%{$search_query}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Get total users count
$count_sql = "SELECT COUNT(*) as total FROM users {$where_clause}";
$count_stmt = $conn->prepare($count_sql);
if ($params) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_users = $count_stmt->get_result()->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_users / $records_per_page);

// Fetch users with filters
$users_sql = "
    SELECT 
        u.user_id,
        u.name,
        u.email,
        u.img_name,
        u.is_active,
        u.last_login,
        u.role,
        u.created_at,
        c.customer_id,
        c.contact_no,
        COUNT(DISTINCT o.order_id) as total_orders
    FROM users u
    LEFT JOIN customers c ON u.user_id = c.user_id
    LEFT JOIN orders o ON c.customer_id = o.customer_id
    {$where_clause}
    GROUP BY u.user_id
    ORDER BY u.created_at DESC
    LIMIT ? OFFSET ?
";

$users_stmt = $conn->prepare($users_sql);
$params[] = $records_per_page;
$params[] = $offset;
$types .= 'ii';

if ($params) {
    $users_stmt->bind_param($types, ...$params);
}
$users_stmt->execute();
$users_result = $users_stmt->get_result();
$users_stmt->close();

// Get statistics
$stats_sql = "
    SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'customer' THEN 1 ELSE 0 END) as customers,
        SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
        SUM(CASE WHEN role = 'staff' THEN 1 ELSE 0 END) as staff,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_users,
        SUM(CASE WHEN last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as active_month
    FROM users
";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

require_once('../../includes/adminHeader.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="users-page">
    <div class="users-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">User Management</h1>
                    <p class="page-subtitle">Manage users, roles, and permissions</p>
                </div>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M19 12H5M12 19l-7-7 7-7"/>
                        </svg>
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Statistics Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon stat-icon-primary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Total Users</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-success">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['customers']); ?></div>
                    <div class="stat-label">Customers</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-info">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['admins'] + $stats['staff']); ?></div>
                    <div class="stat-label">Admin & Staff</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon stat-icon-secondary">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                </div>
                <div class="stat-content">
                    <div class="stat-value"><?php echo number_format($stats['active_users']); ?></div>
                    <div class="stat-label">Active Users</div>
                </div>
            </div>
        </div>

        <!-- Filters and Search -->
        <div class="filters-section">
            <form method="GET" action="users.php" class="filters-form">
                <div class="filter-group">
                    <label for="search" class="filter-label">Search</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        placeholder="Search by name or email..."
                        value="<?php echo htmlspecialchars($search_query); ?>"
                        class="filter-input"
                    >
                </div>

                <div class="filter-group">
                    <label for="role" class="filter-label">Role</label>
                    <select id="role" name="role" class="filter-select">
                        <option value="">All Roles</option>
                        <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
                        <option value="staff" <?php echo $role_filter === 'staff' ? 'selected' : ''; ?>>Staff</option>
                        <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label for="status" class="filter-label">Status</label>
                    <select id="status" name="status" class="filter-select">
                        <option value="">All Status</option>
                        <option value="1" <?php echo $status_filter === '1' ? 'selected' : ''; ?>>Active</option>
                        <option value="0" <?php echo $status_filter === '0' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <div class="filter-actions">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="users.php" class="btn-secondary">Clear</a>
                </div>
            </form>
        </div>

        <!-- Users Table -->
        <div class="users-section">
            <div class="section-header">
                <h2 class="section-title">All Users (<?php echo number_format($total_users); ?>)</h2>
            </div>

            <?php if ($users_result->num_rows > 0): ?>
                <div class="table-wrapper">
                    <table class="users-table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Orders</th>
                                <th>Last Login</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($user = $users_result->fetch_assoc()): ?>
                                <tr data-user-id="<?php echo $user['user_id']; ?>">
                                    <td>
                                        <div class="user-info">
                                            <img 
                                                src="../../user/images/profile_pictures/<?php echo htmlspecialchars($user['img_name'] ?: 'nopfp.jpg'); ?>" 
                                                alt="<?php echo htmlspecialchars($user['name']); ?>"
                                                class="user-avatar"
                                                onerror="this.src='../../user/images/profile_pictures/nopfp.jpg'"
                                            >
                                            <div class="user-details">
                                                <div class="user-name"><?php echo htmlspecialchars($user['name']); ?></div>
                                                <div class="user-meta">ID: <?php echo $user['user_id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                        <?php if ($user['contact_no']): ?>
                                            <div class="user-contact"><?php echo htmlspecialchars($user['contact_no']); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($user['user_id'] === $user_id): ?>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        <?php else: ?>
                                            <select 
                                                class="role-select" 
                                                data-user-id="<?php echo $user['user_id']; ?>"
                                                data-current-role="<?php echo $user['role']; ?>"
                                            >
                                                <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                                                <option value="staff" <?php echo $user['role'] === 'staff' ? 'selected' : ''; ?>>Staff</option>
                                                <?php if ($user_data['role'] === 'admin'): ?>
                                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <?php endif; ?>
                                            </select>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="order-count"><?php echo number_format($user['total_orders']); ?> order<?php echo $user['total_orders'] != 1 ? 's' : ''; ?></span>
                                    </td>
                                    <td>
                                        <?php if ($user['last_login']): ?>
                                            <div class="login-date"><?php echo date('M d, Y', strtotime($user['last_login'])); ?></div>
                                            <div class="login-time"><?php echo date('g:i A', strtotime($user['last_login'])); ?></div>
                                        <?php else: ?>
                                            <span class="no-login">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="status-badge status-<?php echo $user['is_active'] ? 'active' : 'inactive'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($user['user_id'] !== $user_id): ?>
                                                <button 
                                                    class="btn-action btn-toggle-status" 
                                                    data-user-id="<?php echo $user['user_id']; ?>"
                                                    data-current-status="<?php echo $user['is_active']; ?>"
                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> User"
                                                >
                                                    <?php if ($user['is_active']): ?>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                                        </svg>
                                                    <?php else: ?>
                                                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                                        </svg>
                                                    <?php endif; ?>
                                                </button>
                                            <?php else: ?>
                                                <span class="btn-disabled" title="Cannot modify own account">
                                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                                                    </svg>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php
                        $query_params = $_GET;
                        unset($query_params['page']);
                        $query_string = http_build_query($query_params);
                        $query_string = $query_string ? '&' . $query_string : '';
                        ?>

                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo ($page - 1) . $query_string; ?>" class="page-link">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M15 18l-6-6 6-6"/>
                                </svg>
                                Previous
                            </a>
                        <?php endif; ?>

                        <div class="page-numbers">
                            <?php
                            $start = max(1, $page - 2);
                            $end = min($total_pages, $page + 2);
                            
                            for ($i = $start; $i <= $end; $i++):
                            ?>
                                <a 
                                    href="?page=<?php echo $i . $query_string; ?>" 
                                    class="page-number <?php echo $i === $page ? 'active' : ''; ?>"
                                >
                                    <?php echo $i; ?>
                                </a>
                            <?php endfor; ?>
                        </div>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo ($page + 1) . $query_string; ?>" class="page-link">
                                Next
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M9 18l6-6-6-6"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="empty-state">
                    <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <p>No users found</p>
                    <span class="empty-subtext">Try adjusting your filters</span>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Toast Notification -->
    <div id="toast" class="toast"></div>
</main>

<?php require_once('../../includes/footer.php'); ?>

<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Montserrat', sans-serif;
    background: #ffffff;
    color: #1a1a1a;
    line-height: 1.6;
}

.users-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.users-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 40px;
    margin-bottom: 30px;
}

.header-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 400;
    margin-bottom: 5px;
    color: #0a0a0a;
}

.page-subtitle {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
}

.btn-primary,
.btn-secondary {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    cursor: pointer;
    border: none;
}

.btn-primary {
    background: #0a0a0a;
    color: #ffffff;
}

.btn-primary:hover {
    background: #2a2a2a;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #ffffff;
    color: #0a0a0a;
    border: 1px solid rgba(0,0,0,0.15);
}

.btn-secondary:hover {
    background: #fafafa;
    border-color: #0a0a0a;
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    display: flex;
    align-items: center;
    gap: 20px;
    transition: all 0.3s ease;
}

.stat-card:hover {
    border-color: rgba(0,0,0,0.15);
    box-shadow: 0 8px 25px rgba(0,0,0,0.06);
    transform: translateY(-2px);
}

.stat-icon {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon-primary { background: #f0f9ff; color: #1e40af; }
.stat-icon-success { background: #f0fdf4; color: #166534; }
.stat-icon-info { background: #faf5ff; color: #7c3aed; }
.stat-icon-secondary { background: #fafafa; color: #525252; }

.stat-value {
    font-size: 24px;
    font-weight: 600;
    color: #0a0a0a;
    margin-bottom: 4px;
}

.stat-label {
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
}

/* Filters Section */
.filters-section {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 30px;
}

.filters-form {
    display: grid;
    grid-template-columns: 2fr 1fr 1fr auto;
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 9px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    font-weight: 600;
}

.filter-input,
.filter-select {
    padding: 12px 16px;
    font-size: 13px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    color: #0a0a0a;
    font-family: 'Montserrat', sans-serif;
    transition: all 0.3s ease;
}

.filter-input:focus,
.filter-select:focus {
    outline: none;
    border-color: #0a0a0a;
}

.filter-actions {
    display: flex;
    gap: 10px;
}

/* Users Section */
.users-section {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
}

.section-header {
    margin-bottom: 25px;
    padding-bottom: 15px;
    border-bottom: 1px solid rgba(0,0,0,0.06);
}

.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    color: #0a0a0a;
}

/* Users Table */
.table-wrapper {
    overflow-x: auto;
}

.users-table {
    width: 100%;
    border-collapse: collapse;
}

.users-table thead th {
    text-align: left;
    padding: 12px 10px;
    font-size: 9px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.5);
    font-weight: 600;
    border-bottom: 2px solid rgba(0,0,0,0.08);
    background: #fafafa;
}

.users-table tbody td {
    padding: 20px 10px;
    font-size: 13px;
    color: #0a0a0a;
    border-bottom: 1px solid rgba(0,0,0,0.05);
    vertical-align: middle;
}

.users-table tbody tr {
    transition: background 0.2s ease;
}

.users-table tbody tr:hover {
    background: #fafafa;
}

/* User Info */
.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(0,0,0,0.08);
}

.user-details {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.user-name {
    font-weight: 500;
    color: #0a0a0a;
    font-size: 13px;
}

.user-meta {
    font-size: 10px;
    color: rgba(0,0,0,0.4);
    letter-spacing: 0.5px;
}

.user-email {
    font-size: 13px;
    color: #0a0a0a;
}

.user-contact {
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    margin-top: 2px;
}

/* Role Badge & Select */
.role-badge {
    display: inline-block;
    padding: 5px 12px;
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
    border-radius: 2px;
}

.role-customer {
    background: #f0f9ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.role-staff {
    background: #faf5ff;
    color: #7c3aed;
    border: 1px solid #e9d5ff;
}

.role-admin {
    background: #0a0a0a;
    color: #ffffff;
    border: 1px solid #0a0a0a;
}

.role-select {
    padding: 6px 10px;
    font-size: 11px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    color: #0a0a0a;
    font-family: 'Montserrat', sans-serif;
    cursor: pointer;
    transition: all 0.3s ease;
    text-transform: capitalize;
}

.role-select:hover {
    border-color: #0a0a0a;
}

.role-select:focus {
    outline: none;
    border-color: #0a0a0a;
}

/* Order Count */
.order-count {
    font-size: 12px;
    color: rgba(0,0,0,0.7);
}

/* Login Info */
.login-date {
    font-size: 12px;
    color: #0a0a0a;
}

.login-time {
    font-size: 10px;
    color: rgba(0,0,0,0.5);
    margin-top: 2px;
}

.no-login {
    font-size: 11px;
    color: rgba(0,0,0,0.4);
    font-style: italic;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 5px 12px;
    font-size: 9px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 600;
    border-radius: 2px;
}

.status-active {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.status-inactive {
    background: #fef2f2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
}

.btn-action {
    width: 32px;
    height: 32px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.3s ease;
    padding: 0;
}

.btn-action:hover {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-action:hover svg {
    stroke: #ffffff;
}

.btn-disabled {
    width: 32px;
    height: 32px;
    border: 1px solid rgba(0,0,0,0.08);
    background: #fafafa;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.5;
    cursor: not-allowed;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid rgba(0,0,0,0.06);
}

.page-link,
.page-number {
    padding: 10px 16px;
    font-size: 11px;
    letter-spacing: 0.5px;
    text-decoration: none;
    color: #0a0a0a;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
}

.page-link:hover,
.page-number:hover {
    background: #fafafa;
    border-color: #0a0a0a;
}

.page-number.active {
    background: #0a0a0a;
    color: #ffffff;
    border-color: #0a0a0a;
}

.page-numbers {
    display: flex;
    gap: 5px;
}

/* Empty State */
.empty-state {
    text-align: center;
    padding: 60px 20px;
}

.empty-state svg {
    opacity: 0.2;
    margin-bottom: 20px;
}

.empty-state p {
    font-size: 14px;
    color: rgba(0,0,0,0.7);
    margin-bottom: 5px;
}

.empty-subtext {
    font-size: 11px;
    color: rgba(0,0,0,0.4);
    letter-spacing: 0.3px;
}

/* Toast Notification */
.toast {
    position: fixed;
    bottom: 30px;
    right: 30px;
    padding: 16px 24px;
    background: #0a0a0a;
    color: #ffffff;
    font-size: 12px;
    letter-spacing: 0.5px;
    border-radius: 4px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.3);
    opacity: 0;
    transform: translateY(20px);
    transition: all 0.3s ease;
    pointer-events: none;
    z-index: 10000;
}

.toast.show {
    opacity: 1;
    transform: translateY(0);
    pointer-events: auto;
}

.toast.success {
    background: #166534;
}

.toast.error {
    background: #b91c1c;
}

/* Responsive Design */
@media (max-width: 1200px) {
    .filters-form {
        grid-template-columns: 1fr 1fr;
    }

    .filter-actions {
        grid-column: span 2;
        justify-content: flex-start;
    }
}

@media (max-width: 768px) {
    .users-page {
        padding: 80px 20px 50px;
    }

    .page-header {
        padding: 30px 25px;
    }

    .header-content {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }

    .page-title {
        font-size: 26px;
    }

    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }

    .stat-card {
        padding: 20px;
    }

    .stat-icon {
        width: 40px;
        height: 40px;
    }

    .stat-value {
        font-size: 20px;
    }

    .filters-section {
        padding: 25px 20px;
    }

    .filters-form {
        grid-template-columns: 1fr;
    }

    .filter-actions {
        grid-column: span 1;
        flex-direction: column;
    }

    .filter-actions .btn-primary,
    .filter-actions .btn-secondary {
        width: 100%;
        justify-content: center;
    }

    .users-section {
        padding: 25px 20px;
    }

    .table-wrapper {
        margin: 0 -20px;
        padding: 0 20px;
    }

    .users-table {
        font-size: 11px;
    }

    .users-table thead th,
    .users-table tbody td {
        padding: 12px 8px;
        font-size: 10px;
    }

    .user-avatar {
        width: 32px;
        height: 32px;
    }

    .user-name {
        font-size: 11px;
    }

    .pagination {
        flex-wrap: wrap;
    }

    .toast {
        bottom: 20px;
        right: 20px;
        left: 20px;
    }
}

@media (max-width: 480px) {
    .page-title {
        font-size: 22px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }

    .users-table thead {
        display: none;
    }

    .users-table tbody tr {
        display: block;
        margin-bottom: 15px;
        border: 1px solid rgba(0,0,0,0.08);
        padding: 15px;
    }

    .users-table tbody td {
        display: flex;
        justify-content: space-between;
        padding: 8px 0;
        border: none;
        border-bottom: 1px solid rgba(0,0,0,0.05);
    }

    .users-table tbody td:last-child {
        border-bottom: none;
    }

    .users-table tbody td::before {
        content: attr(data-label);
        font-size: 9px;
        letter-spacing: 1px;
        text-transform: uppercase;
        color: rgba(0,0,0,0.5);
        font-weight: 600;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toast notification function
    function showToast(message, type = 'success') {
        const toast = document.getElementById('toast');
        toast.textContent = message;
        toast.className = 'toast show ' + type;
        
        setTimeout(() => {
            toast.className = 'toast';
        }, 3000);
    }

    // Handle role change
    document.querySelectorAll('.role-select').forEach(select => {
        select.addEventListener('change', function() {
            const userId = this.dataset.userId;
            const newRole = this.value;
            const currentRole = this.dataset.currentRole;
            
            if (confirm(`Are you sure you want to change this user's role to ${newRole}?`)) {
                fetch('users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_role&user_id=${userId}&role=${newRole}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        this.dataset.currentRole = newRole;
                    } else {
                        showToast(data.message, 'error');
                        this.value = currentRole;
                    }
                })
                .catch(error => {
                    showToast('An error occurred. Please try again.', 'error');
                    this.value = currentRole;
                });
            } else {
                this.value = currentRole;
            }
        });
    });

    // Handle status toggle
    document.querySelectorAll('.btn-toggle-status').forEach(button => {
        button.addEventListener('click', function() {
            const userId = this.dataset.userId;
            const currentStatus = parseInt(this.dataset.currentStatus);
            const newStatus = currentStatus === 1 ? 0 : 1;
            const action = newStatus === 1 ? 'activate' : 'deactivate';
            
            if (confirm(`Are you sure you want to ${action} this user?`)) {
                fetch('users.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=toggle_active&user_id=${userId}&is_active=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showToast(data.message, 'success');
                        
                        // Update button
                        this.dataset.currentStatus = newStatus;
                        this.title = newStatus === 1 ? 'Deactivate User' : 'Activate User';
                        
                        // Update icon
                        if (newStatus === 1) {
                            this.innerHTML = `
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/>
                                </svg>
                            `;
                        } else {
                            this.innerHTML = `
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            `;
                        }
                        
                        // Update status badge
                        const row = this.closest('tr');
                        const statusBadge = row.querySelector('.status-badge');
                        if (newStatus === 1) {
                            statusBadge.className = 'status-badge status-active';
                            statusBadge.textContent = 'Active';
                        } else {
                            statusBadge.className = 'status-badge status-inactive';
                            statusBadge.textContent = 'Inactive';
                        }
                    } else {
                        showToast(data.message, 'error');
                    }
                })
                .catch(error => {
                    showToast('An error occurred. Please try again.', 'error');
                });
            }
        });
    });
});
</script>

<?php ob_end_flush(); ?>