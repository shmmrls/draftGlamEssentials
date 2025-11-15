document.addEventListener('DOMContentLoaded', function() {
    console.log('Form validation script loaded');
    
    // Get form elements
    const form = document.getElementById('createCategoryForm') || document.getElementById('editCategoryForm');
    const categoryNameInput = document.getElementById('category_name');
    const imgNameInput = document.getElementById('img_name');
    const categoryImageInput = document.getElementById('categoryImage');
    const imagePreview = document.getElementById('imagePreview');
    const imagePreviewContainer = document.getElementById('imagePreviewContainer');
    const fileName = document.querySelector('.file-name');
    
    console.log('Form found:', !!form);
    console.log('Category name input:', !!categoryNameInput);
    console.log('Image input:', !!categoryImageInput);

    // Error message elements
    const errorCategoryName = document.getElementById('error_category_name');
    const errorImgName = document.getElementById('error_img_name');
    const errorCategoryImage = document.getElementById('error_category_image');

    // Validation functions
    function validateCategoryName() {
        if (!categoryNameInput) return true;
        
        const value = categoryNameInput.value.trim();
        
        if (value === '') {
            showError(categoryNameInput, errorCategoryName, 'Category name is required.');
            return false;
        }
        
        if (value.length < 2) {
            showError(categoryNameInput, errorCategoryName, 'Category name must be at least 2 characters.');
            return false;
        }
        
        if (value.length > 64) {
            showError(categoryNameInput, errorCategoryName, 'Category name must not exceed 64 characters.');
            return false;
        }
        
        clearError(categoryNameInput, errorCategoryName);
        return true;
    }

    function validateImgName() {
        if (!imgNameInput) return true;
        
        const value = imgNameInput.value.trim();
        
        // Image name is optional, so empty is valid
        if (value === '') {
            clearError(imgNameInput, errorImgName);
            return true;
        }
        
        // Must contain only letters, numbers, underscores, and hyphens
        const pattern = /^[a-zA-Z0-9_\-]+$/;
        if (!pattern.test(value)) {
            showError(imgNameInput, errorImgName, 'Image name can only contain letters, numbers, underscores, and hyphens.');
            return false;
        }
        
        if (value.length > 100) {
            showError(imgNameInput, errorImgName, 'Image name must not exceed 100 characters.');
            return false;
        }
        
        clearError(imgNameInput, errorImgName);
        return true;
    }

    function validateCategoryImage() {
        if (!categoryImageInput) return true;
        
        if (!categoryImageInput.files || categoryImageInput.files.length === 0) {
            // Image is optional in edit mode
            clearError(categoryImageInput, errorCategoryImage);
            return true;
        }

        const file = categoryImageInput.files[0];
        const maxSize = 5 * 1024 * 1024; // 5MB
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

        if (!allowedTypes.includes(file.type)) {
            showError(categoryImageInput, errorCategoryImage, 'Only JPG, PNG, and WEBP images are allowed.');
            return false;
        }

        if (file.size > maxSize) {
            showError(categoryImageInput, errorCategoryImage, 'Image size must not exceed 5MB.');
            return false;
        }

        clearError(categoryImageInput, errorCategoryImage);
        return true;
    }

    function showError(input, errorElement, message) {
        if (!input || !errorElement) return;
        input.classList.add('error');
        errorElement.textContent = message;
        errorElement.classList.add('show');
    }

    function clearError(input, errorElement) {
        if (!input || !errorElement) return;
        input.classList.remove('error');
        errorElement.textContent = '';
        errorElement.classList.remove('show');
    }

    // Image preview functionality
    if (categoryImageInput && imagePreview && imagePreviewContainer && fileName) {
        categoryImageInput.addEventListener('change', function(e) {
            console.log('File selected:', this.files);
            
            if (this.files && this.files.length > 0) {
                const file = this.files[0];
                console.log('File details:', {
                    name: file.name,
                    size: file.size,
                    type: file.type
                });
                
                fileName.textContent = file.name;

                // Validate the image
                const isValid = validateCategoryImage();
                console.log('Validation result:', isValid);

                // Show preview if it's a valid image
                if (isValid && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        console.log('Image loaded for preview');
                        imagePreview.src = e.target.result;
                        imagePreviewContainer.style.display = 'block';
                    }
                    reader.onerror = function(e) {
                        console.error('FileReader error:', e);
                    }
                    reader.readAsDataURL(file);
                } else {
                    imagePreviewContainer.style.display = 'none';
                }
            } else {
                fileName.textContent = 'No file chosen';
                imagePreviewContainer.style.display = 'none';
            }
        });
    }

    // Real-time validation
    if (categoryNameInput) {
        categoryNameInput.addEventListener('blur', validateCategoryName);
        categoryNameInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateCategoryName();
            }
        });
    }

    if (imgNameInput) {
        imgNameInput.addEventListener('blur', validateImgName);
        imgNameInput.addEventListener('input', function() {
            if (this.classList.contains('error')) {
                validateImgName();
            }
        });
    }

    // Form submission validation
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('=== FORM SUBMISSION STARTED ===');
            console.log('Form element:', form);
            console.log('Form action:', form.action);
            console.log('Form method:', form.method);
            console.log('Form enctype:', form.enctype);
            
            // Log form data
            const formData = new FormData(this);
            console.log('Form data entries:');
            for (let pair of formData.entries()) {
                if (pair[1] instanceof File) {
                    console.log(pair[0] + ': [File]', pair[1].name, pair[1].size + ' bytes', pair[1].type);
                } else {
                    console.log(pair[0] + ': ', pair[1]);
                }
            }
            
            // Validate all fields
            const isCategoryNameValid = validateCategoryName();
            const isImgNameValid = validateImgName();
            const isCategoryImageValid = validateCategoryImage();

            console.log('Validation results:', {
                categoryName: isCategoryNameValid,
                imgName: isImgNameValid,
                categoryImage: isCategoryImageValid
            });

            // If validation fails, prevent submission
            if (!isCategoryNameValid || !isImgNameValid || !isCategoryImageValid) {
                e.preventDefault();
                console.error('❌ Form submission prevented due to validation errors');
                
                // Scroll to first error
                const firstError = document.querySelector('.form-input.error');
                if (firstError) {
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    firstError.focus();
                }
                return false;
            }
            
            // All validations passed - form will submit normally
            console.log('✅ All validations passed - form will submit');
            console.log('=== FORM SUBMISSION ALLOWED ===');
            // Don't prevent default - let form submit naturally
        });
    }

    // Auto-generate image name from category name (optional helper)
    if (categoryNameInput && imgNameInput) {
        categoryNameInput.addEventListener('input', function() {
            // Only auto-generate if img_name is empty
            if (imgNameInput.value.trim() === '') {
                const sanitized = this.value.trim()
                    .toLowerCase()
                    .replace(/[^a-z0-9\s\-]/g, '')
                    .replace(/\s+/g, '_')
                    .replace(/_+/g, '_')
                    .replace(/^_|_$/g, '');
                imgNameInput.value = sanitized;
                console.log('Auto-generated img_name:', sanitized);
            }
        });
    }
});