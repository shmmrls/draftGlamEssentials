<?php
ob_start();
session_start();
require_once('../../includes/config.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "Please login to access this page.";
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Fetch user role
$user_stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_result = $user_stmt->get_result();
$user_data = $user_result->fetch_assoc();
$user_stmt->close();

// Only admin can access this page
if ($user_data['role'] !== 'admin') {
    $_SESSION['error_message'] = "You do not have permission to access this page.";
    header("Location: ../dashboard.php");
    exit;
}

$success_message = '';
$error_message = '';

// Handle Add Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $img_name = trim($_POST['img_name']);
    
    if (!empty($category_name)) {
        // Check if category already exists
        $check_stmt = $conn->prepare("SELECT category_id FROM categories WHERE category_name = ?");
        $check_stmt->bind_param("s", $category_name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "Category already exists.";
        } else {
            $insert_stmt = $conn->prepare("INSERT INTO categories (category_name, img_name) VALUES (?, ?)");
            $insert_stmt->bind_param("ss", $category_name, $img_name);
            
            if ($insert_stmt->execute()) {
                $success_message = "Category '$category_name' added successfully!";
            } else {
                $error_message = "Error adding category.";
            }
            $insert_stmt->close();
        }
        $check_stmt->close();
    } else {
        $error_message = "Category name is required.";
    }
}

// Handle Update Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_id = (int) $_POST['category_id'];
    $category_name = trim($_POST['category_name']);
    $img_name = trim($_POST['img_name']);
    
    if (!empty($category_name)) {
        $update_stmt = $conn->prepare("UPDATE categories SET category_name = ?, img_name = ? WHERE category_id = ?");
        $update_stmt->bind_param("ssi", $category_name, $img_name, $category_id);
        
        if ($update_stmt->execute()) {
            $success_message = "Category updated successfully!";
        } else {
            $error_message = "Error updating category.";
        }
        $update_stmt->close();
    } else {
        $error_message = "Category name is required.";
    }
}

// Handle Delete Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
    $category_id = (int) $_POST['category_id'];
    
    // Check if category has products
    $check_products = $conn->prepare("SELECT COUNT(*) as product_count FROM products WHERE category_id = ?");
    $check_products->bind_param("i", $category_id);
    $check_products->execute();
    $product_result = $check_products->get_result();
    $product_data = $product_result->fetch_assoc();
    $check_products->close();
    
    if ($product_data['product_count'] > 0) {
        $error_message = "Cannot delete category with existing products. Please remove or reassign products first.";
    } else {
        $delete_stmt = $conn->prepare("DELETE FROM categories WHERE category_id = ?");
        $delete_stmt->bind_param("i", $category_id);
        
        if ($delete_stmt->execute()) {
            $success_message = "Category deleted successfully!";
        } else {
            $error_message = "Error deleting category.";
        }
        $delete_stmt->close();
    }
}

// Get search parameter
$search_query = $_GET['search'] ?? '';

// Build query with search
$sql = "SELECT 
    c.category_id,
    c.category_name,
    c.img_name,
    COUNT(p.product_id) as product_count
FROM categories c
LEFT JOIN products p ON c.category_id = p.category_id
WHERE 1=1";

$params = [];
$types = "";

if (!empty($search_query)) {
    $sql .= " AND c.category_name LIKE ?";
    $search_param = "%$search_query%";
    $params[] = $search_param;
    $types .= "s";
}

$sql .= " GROUP BY c.category_id ORDER BY c.category_name ASC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$categories_result = $stmt->get_result();
$stmt->close();

require_once('../../includes/adminHeader.php');
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="categories-page">
    <div class="categories-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <a href="../dashboard.php" class="back-link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                    </svg>
                    Back to Dashboard
                </a>
                <h1 class="page-title">Category Management</h1>
                <p class="page-subtitle">Organize and manage product categories</p>
            </div>
            <button class="btn btn-add" onclick="openAddModal()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>
                </svg>
                Add Category
            </button>
        </div>

        <!-- Messages -->
        <?php if (!empty($success_message)): ?>
        <div class="alert alert-success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="20 6 9 17 4 12"/>
            </svg>
            <span><?php echo $success_message; ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
        <div class="alert alert-error">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
            </svg>
            <span><?php echo $error_message; ?></span>
        </div>
        <?php endif; ?>

        <!-- Search -->
        <div class="filters-container">
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label for="search" class="filter-label">Search Categories</label>
                    <input 
                        type="text" 
                        id="search" 
                        name="search" 
                        class="filter-input"
                        placeholder="Category name..."
                        value="<?php echo htmlspecialchars($search_query); ?>"
                    >
                </div>

                <button type="submit" class="btn btn-filter">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>
                    </svg>
                    Search
                </button>

                <?php if (!empty($search_query)): ?>
                <a href="categories.php" class="btn btn-clear">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="categories-table-container">
            <?php if ($categories_result->num_rows > 0): ?>
            <table class="categories-table">
                <thead>
                    <tr>
                        <th>Category ID</th>
                        <th>Category Name</th>
                        <th>Image Name</th>
                        <th>Products</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($category = $categories_result->fetch_assoc()): ?>
                    <tr>
                        <td class="category-id">#<?php echo $category['category_id']; ?></td>
                        <td class="category-name"><?php echo htmlspecialchars($category['category_name']); ?></td>
                        <td class="img-name"><?php echo htmlspecialchars($category['img_name'] ?? 'N/A'); ?></td>
                        <td class="product-count">
                            <span class="count-badge"><?php echo $category['product_count']; ?> product(s)</span>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="btn-action btn-edit" onclick='editCategory(<?php echo json_encode($category); ?>)'>
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                        <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                    </svg>
                                    Edit
                                </button>
                                <button class="btn-action btn-delete" onclick="deleteCategory(<?php echo $category['category_id']; ?>, '<?php echo htmlspecialchars($category['category_name']); ?>', <?php echo $category['product_count']; ?>)">
                                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    </svg>
                                    Delete
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="no-categories">
                <svg width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                </svg>
                <h3>No Categories Found</h3>
                <p>There are no categories matching your criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add/Edit Category Modal -->
<div id="categoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 class="modal-title" id="modalTitle">Add New Category</h2>
            <button class="modal-close" onclick="closeModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="categoryForm">
                <input type="hidden" name="category_id" id="category_id">
                
                <div class="form-group">
                    <label for="category_name" class="form-label">
                        Category Name <span class="required">*</span>
                    </label>
                    <input 
                        type="text" 
                        id="category_name" 
                        name="category_name" 
                        class="form-input"
                        placeholder="e.g., Hair Care, Skincare"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="img_name" class="form-label">
                        Image Name <span class="optional">(optional)</span>
                    </label>
                    <input 
                        type="text" 
                        id="img_name" 
                        name="img_name" 
                        class="form-input"
                        placeholder="e.g., hair_care, skincare"
                    >
                    <small class="form-hint">Image filename without extension</small>
                </div>

                <div class="form-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Cancel</button>
                    <button type="submit" name="add_category" id="submitBtn" class="btn btn-submit">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"/>
                        </svg>
                        Add Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content modal-small">
        <div class="modal-header">
            <h2 class="modal-title">Confirm Delete</h2>
            <button class="modal-close" onclick="closeDeleteModal()">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="delete-warning">
                <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                    <circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>
                </svg>
                <p id="deleteMessage"></p>
            </div>
            <form method="POST" action="" id="deleteForm">
                <input type="hidden" name="category_id" id="delete_category_id">
                <div class="form-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_category" class="btn btn-delete-confirm">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Delete Category
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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

.categories-page {
    min-height: 100vh;
    padding: 100px 30px 60px;
    background: linear-gradient(to bottom, #fafafa 0%, #ffffff 100%);
}

.categories-container {
    max-width: 1400px;
    margin: 0 auto;
}

/* Page Header */
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-end;
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 40px;
    margin-bottom: 30px;
}

.header-content {
    flex: 1;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    text-decoration: none;
    margin-bottom: 20px;
    transition: color 0.3s ease;
}

.back-link:hover {
    color: #0a0a0a;
}

.page-title {
    font-family: 'Playfair Display', serif;
    font-size: 32px;
    font-weight: 400;
    margin-bottom: 8px;
    color: #0a0a0a;
}

.page-subtitle {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
}

.btn-add {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-add:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

/* Alerts */
.alert {
    display: flex;
    align-items: flex-start;
    gap: 15px;
    padding: 20px 25px;
    margin-bottom: 30px;
    border-left: 3px solid;
    font-size: 13px;
    letter-spacing: 0.3px;
}

.alert svg {
    flex-shrink: 0;
    margin-top: 2px;
}

.alert-success {
    background: #f0fdf4;
    border-color: #166534;
    color: #166534;
}

.alert-error {
    background: #fef2f2;
    border-color: #b91c1c;
    color: #b91c1c;
}

/* Filters */
.filters-container {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    padding: 30px;
    margin-bottom: 30px;
}

.filters-form {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 20px;
    align-items: end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.filter-label {
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.6);
    font-weight: 500;
}

.filter-input {
    padding: 12px 15px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 13px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
    transition: all 0.3s ease;
}

.filter-input:focus {
    outline: none;
    border-color: #0a0a0a;
    background: #fafafa;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 24px;
    border: 1px solid;
    font-size: 10px;
    letter-spacing: 2px;
    text-transform: uppercase;
    font-weight: 500;
    font-family: 'Montserrat', sans-serif;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.3s ease;
    white-space: nowrap;
}

.btn-filter {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-filter:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.btn-clear {
    background: transparent;
    border-color: rgba(0,0,0,0.15);
    color: #0a0a0a;
}

.btn-clear:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

/* Categories Table */
.categories-table-container {
    background: #ffffff;
    border: 1px solid rgba(0,0,0,0.08);
    overflow-x: auto;
}

.categories-table {
    width: 100%;
    border-collapse: collapse;
}

.categories-table thead {
    background: #fafafa;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.categories-table th {
    padding: 20px 25px;
    text-align: left;
    font-size: 10px;
    letter-spacing: 1.5px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 600;
}

.categories-table td {
    padding: 20px 25px;
    font-size: 13px;
    color: #1a1a1a;
    border-bottom: 1px solid rgba(0,0,0,0.05);
}

.categories-table tbody tr {
    transition: background-color 0.2s ease;
}

.categories-table tbody tr:hover {
    background: #fafafa;
}

.category-id {
    font-weight: 600;
    color: #0a0a0a;
}

.category-name {
    font-weight: 500;
}

.img-name {
    font-family: monospace;
    font-size: 12px;
    color: rgba(0,0,0,0.6);
}

.count-badge {
    display: inline-block;
    padding: 6px 12px;
    background: #f3f4f6;
    color: #374151;
    font-size: 11px;
    letter-spacing: 0.5px;
    border-radius: 2px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 10px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    background: transparent;
    border: 1px solid rgba(0,0,0,0.15);
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #0a0a0a;
}

.btn-action:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

.btn-delete {
    color: #b91c1c;
    border-color: rgba(185, 28, 28, 0.3);
}

.btn-delete:hover {
    background: #fef2f2;
    border-color: #b91c1c;
}

/* No Categories */
.no-categories {
    padding: 80px 40px;
    text-align: center;
}

.no-categories svg {
    margin-bottom: 20px;
    color: rgba(0,0,0,0.2);
}

.no-categories h3 {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    margin-bottom: 10px;
    color: #0a0a0a;
}

.no-categories p {
    font-size: 13px;
    color: rgba(0,0,0,0.5);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    overflow-y: auto;
    padding: 40px 20px;
}

.modal.show {
    display: flex;
    align-items: center;
    justify-content: center;
}

.modal-content {
    background: #ffffff;
    width: 100%;
    max-width: 600px;
    margin: auto;
}

.modal-small {
    max-width: 500px;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 30px 40px;
    border-bottom: 1px solid rgba(0,0,0,0.08);
}

.modal-title {
    font-family: 'Playfair Display', serif;
    font-size: 24px;
    font-weight: 400;
    color: #0a0a0a;
}

.modal-close {
    background: none;
    border: none;
    cursor: pointer;
    padding: 5px;
    color: rgba(0,0,0,0.5);
    transition: color 0.3s ease;
}

.modal-close:hover {
    color: #0a0a0a;
}

.modal-body {
    padding: 40px;
}

/* Form Styles */
.form-group {
    margin-bottom: 25px;
}

.form-label {
    display: block;
    font-size: 11px;
    letter-spacing: 1px;
    text-transform: uppercase;
    color: rgba(0,0,0,0.7);
    font-weight: 500;
    margin-bottom: 10px;
}

.required {
    color: #b91c1c;
}

.optional {
    color: rgba(0,0,0,0.4);
    text-transform: lowercase;
}

.form-input {
    width: 100%;
    padding: 14px 16px;
    border: 1px solid rgba(0,0,0,0.15);
    background: #ffffff;
    font-size: 13px;
    font-family: 'Montserrat', sans-serif;
    color: #0a0a0a;
    transition: all 0.3s ease;
}

.form-input:focus {
    outline: none;
    border-color: #0a0a0a;
    background: #fafafa;
}

.form-hint {
    display: block;
    margin-top: 8px;
    font-size: 11px;
    color: rgba(0,0,0,0.5);
    letter-spacing: 0.3px;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    margin-top: 35px;
    padding-top: 25px;
    border-top: 1px solid rgba(0,0,0,0.08);
}

.btn-cancel {
    background: transparent;
    border-color: rgba(0,0,0,0.15);
    color: #0a0a0a;
}

.btn-cancel:hover {
    border-color: #0a0a0a;
    background: #fafafa;
}

.btn-submit {
    background: #0a0a0a;
    border-color: #0a0a0a;
    color: #ffffff;
}

.btn-submit:hover {
    background: #2a2a2a;
    border-color: #2a2a2a;
}

.btn-delete-confirm {
    background: #b91c1c;
    border-color: #b91c1c;
    color: #ffffff;
}

.btn-delete-confirm:hover {
    background: #991b1b;
    border-color: #991b1b;
}

/* Delete Warning */
.delete-warning {
    text-align: center;
    padding: 20px 0;
}

.delete-warning svg {
    color: #f59e0b;
    margin-bottom: 20px;
}

.delete-warning p {
    font-size: 14px;
    color: #1a1a1a;
    line-height: 1.6;
}

/* Responsive */
@media (max-width: 1200px) {
    .filters-form {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .categories-page {
        padding: 80px 20px 50px;
    }

    .page-header {
        flex-direction: column;
        align-items: flex-start;
        padding: 30px 25px;
        gap: 20px;
    }

    .page-title {
        font-size: 26px;
    }

    .filters-container {
        padding: 25px 20px;
    }

    .categories-table th,
    .categories-table td {
        padding: 15px;
        font-size: 12px;
    }

    .action-buttons {
        flex-direction: column;
        gap: 8px;
    }

    .btn-action {
        width: 100%;
        justify-content: center;
    }

    .modal-header,
    .modal-body {
        padding: 25px;
    }

    .form-actions {
        flex-direction: column;
    }

    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

<script>
function openAddModal() {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    // Reset form
    form.reset();
    document.getElementById('category_id').value = '';
    
    // Set to add mode
    modalTitle.textContent = 'Add New Category';
    submitBtn.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Add Category
    `;
    submitBtn.name = 'add_category';
    
    modal.classList.add('show');
}

function editCategory(category) {
    const modal = document.getElementById('categoryModal');
    const form = document.getElementById('categoryForm');
    const modalTitle = document.getElementById('modalTitle');
    const submitBtn = document.getElementById('submitBtn');
    
    // Fill form with category data
    document.getElementById('category_id').value = category.category_id;
    document.getElementById('category_name').value = category.category_name;
    document.getElementById('img_name').value = category.img_name || '';
    
    // Set to edit mode
    modalTitle.textContent = 'Edit Category';
    submitBtn.innerHTML = `
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="20 6 9 17 4 12"/>
        </svg>
        Update Category
    `;
    submitBtn.name = 'update_category';
    
    modal.classList.add('show');
}

function closeModal() {
    const modal = document.getElementById('categoryModal');
    modal.classList.remove('show');
}

function deleteCategory(categoryId, categoryName, productCount) {
    const modal = document.getElementById('deleteModal');
    const deleteMessage = document.getElementById('deleteMessage');
    const deleteCategoryId = document.getElementById('delete_category_id');
    
    deleteCategoryId.value = categoryId;
    
    if (productCount > 0) {
        deleteMessage.innerHTML = `
            <strong>Warning:</strong> Cannot delete category "<strong>${categoryName}</strong>" because it contains <strong>${productCount}</strong> product(s).<br><br>
            Please remove or reassign all products before deleting this category.
        `;
        // Hide delete button, show only cancel
        document.querySelector('.btn-delete-confirm').style.display = 'none';
    } else {
        deleteMessage.innerHTML = `
            Are you sure you want to delete the category "<strong>${categoryName}</strong>"?<br><br>
            This action cannot be undone.
        `;
        document.querySelector('.btn-delete-confirm').style.display = 'inline-flex';
    }
    
    modal.classList.add('show');
}

function closeDeleteModal() {
    const modal = document.getElementById('deleteModal');
    modal.classList.remove('show');
}

// Close modals when clicking outside
document.getElementById('categoryModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
    }
});

document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeDeleteModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal();
        closeDeleteModal();
    }
});

// Form validation
document.getElementById('categoryForm').addEventListener('submit', function(e) {
    const categoryName = document.getElementById('category_name').value.trim();
    
    if (categoryName === '') {
        e.preventDefault();
        alert('Category name is required.');
        return false;
    }
    
    if (categoryName.length < 2) {
        e.preventDefault();
        alert('Category name must be at least 2 characters long.');
        return false;
    }
    
    if (categoryName.length > 64) {
        e.preventDefault();
        alert('Category name must not exceed 64 characters.');
        return false;
    }
    
    // Validate image name if provided
    const imgName = document.getElementById('img_name').value.trim();
    if (imgName !== '') {
        // Check for valid characters (alphanumeric, underscore, hyphen)
        const validImgName = /^[a-zA-Z0-9_-]+$/;
        if (!validImgName.test(imgName)) {
            e.preventDefault();
            alert('Image name can only contain letters, numbers, underscores, and hyphens.');
            return false;
        }
    }
});

// Auto-dismiss alerts after 5 seconds
document.addEventListener('DOMContentLoaded', function() {
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.style.display = 'none';
            }, 500);
        }, 5000);
    });
});
</script>

<?php ob_end_flush(); ?>