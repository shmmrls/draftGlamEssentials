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

$pageCss = $baseUrl . '/admin/categories/css/categories.css';
include '../../includes/adminHeader.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600&family=Playfair+Display:wght@400;500&display=swap" rel="stylesheet">

<main class="create-page">
    <div class="create-container">
        <?php include '../../includes/alert.php'; ?>
        
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-info">
                    <h1 class="page-title">Add New Category</h1>
                    <p class="page-subtitle">Create a new product category</p>
                </div>
                <div class="header-actions">
                    <a href="<?php echo $baseUrl; ?>/admin/categories/index.php" class="btn-secondary">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/>
                        </svg>
                        Back to Categories
                    </a>
                </div>
            </div>
        </div>

        <!-- Create Form -->
        <form action="<?php echo $baseUrl; ?>/admin/categories/store.php" method="post" enctype="multipart/form-data" class="create-form" id="createCategoryForm">
            
            <!-- Basic Information Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Basic Information</h2>
                    <p class="section-description">Essential category details</p>
                </div>
                
                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Category Name *</label>
                        <input type="text" name="category_name" id="category_name" required class="form-input" placeholder="Enter category name" />
                        <span class="error-message" id="error_category_name"></span>
                    </div>

                    <div class="form-group full-width">
                        <label class="form-label">Image Name</label>
                        <input type="text" name="img_name" id="img_name" class="form-input" placeholder="e.g. hair_care" />
                        <small class="form-hint">Base name without extension (e.g., "hair_care"). Leave empty to auto-generate.</small>
                        <span class="error-message" id="error_img_name"></span>
                    </div>
                </div>
            </div>

            <!-- Category Image Section -->
            <div class="form-section">
                <div class="section-header">
                    <h2 class="section-title">Category Image</h2>
                    <p class="section-description">Upload an image to represent this category</p>
                </div>

                <div class="form-grid">
                    <div class="form-group full-width">
                        <label class="form-label">Upload Category Image</label>
                        <div class="file-upload-wrapper">
                            <input type="file" name="category_image" accept="image/*" class="file-input" id="categoryImage" />
                            <label for="categoryImage" class="file-label">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/>
                                </svg>
                                Choose Image
                            </label>
                            <span class="file-name">No file chosen</span>
                        </div>
                        <small class="form-hint">Recommended: Square image, at least 800x800px, max 5MB</small>
                        <span class="error-message" id="error_category_image"></span>
                    </div>

                    <div class="form-group full-width" id="imagePreviewContainer" style="display: none;">
                        <label class="form-label">Preview</label>
                        <div class="image-preview">
                            <img id="imagePreview" src="" alt="Preview" class="preview-img" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="submit" class="btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                    </svg>
                    Create Category
                </button>
                <a href="<?php echo $baseUrl; ?>/admin/categories/index.php" class="btn-cancel">Cancel</a>
            </div>
        </form>
    </div>
</main>

<script src="<?php echo $baseUrl; ?>/admin/categories/js/form-validation.js"></script>

<?php require_once('../../includes/footer.php'); ?>