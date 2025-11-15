/**
 * Form Validation Library - No HTML5 validation
 * Provides JavaScript-only form validation
 */

// Disable HTML5 validation on all forms
document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.setAttribute('novalidate', 'novalidate');
    });
});

/**
 * Validation Rules
 */
const ValidationRules = {
    required: (value) => {
        return value.trim() !== '';
    },
    
    email: (value) => {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return emailRegex.test(value);
    },
    
    minLength: (value, min) => {
        return value.length >= min;
    },
    
    maxLength: (value, max) => {
        return value.length <= max;
    },
    
    password: (value) => {
        // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
        const passwordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{8,}$/;
        return passwordRegex.test(value);
    },
    
    passwordMatch: (password, confirmPassword) => {
        return password === confirmPassword;
    },
    
    phone: (value) => {
        // Philippine phone number format (09XXXXXXXXX or +639XXXXXXXXX)
        const phoneRegex = /^(\+639|09)\d{9}$/;
        return phoneRegex.test(value.replace(/\s+/g, ''));
    },
    
    alphaNumeric: (value) => {
        const alphaNumericRegex = /^[a-zA-Z0-9\s]+$/;
        return alphaNumericRegex.test(value);
    },
    
    alpha: (value) => {
        const alphaRegex = /^[a-zA-Z\s]+$/;
        return alphaRegex.test(value);
    },
    
    numeric: (value) => {
        return !isNaN(value) && value.trim() !== '';
    },
    
    fileSize: (file, maxSizeMB) => {
        if (!file) return true;
        const maxBytes = maxSizeMB * 1024 * 1024;
        return file.size <= maxBytes;
    },
    
    fileType: (file, allowedTypes) => {
        if (!file) return true;
        const fileExtension = file.name.split('.').pop().toLowerCase();
        return allowedTypes.includes(fileExtension);
    }
};

/**
 * Error Messages
 */
const ErrorMessages = {
    required: 'This field is required',
    email: 'Please enter a valid email address',
    minLength: (min) => `Must be at least ${min} characters`,
    maxLength: (max) => `Must not exceed ${max} characters`,
    password: 'Password must be at least 8 characters with 1 uppercase, 1 lowercase, and 1 number',
    passwordMatch: 'Passwords do not match',
    phone: 'Please enter a valid Philippine phone number (09XXXXXXXXX)',
    alphaNumeric: 'Only letters and numbers are allowed',
    alpha: 'Only letters are allowed',
    numeric: 'Only numbers are allowed',
    fileSize: (maxSizeMB) => `File size must not exceed ${maxSizeMB}MB`,
    fileType: (types) => `Allowed file types: ${types.join(', ')}`
};

/**
 * Display error message
 */
function showError(input, message) {
    // Remove existing error
    clearError(input);
    
    // Add error class to input
    input.classList.add('error');
    input.classList.remove('success');
    
    // Create error message element
    const errorDiv = document.createElement('div');
    errorDiv.className = 'error-message';
    errorDiv.textContent = message;
    errorDiv.style.color = '#b91c1c';
    errorDiv.style.fontSize = '12px';
    errorDiv.style.marginTop = '5px';
    errorDiv.style.display = 'block';
    
    // Insert error message after input
    input.parentNode.insertBefore(errorDiv, input.nextSibling);
}

/**
 * Clear error message
 */
function clearError(input) {
    input.classList.remove('error');
    const errorMsg = input.parentNode.querySelector('.error-message');
    if (errorMsg) {
        errorMsg.remove();
    }
}

/**
 * Show success state
 */
function showSuccess(input) {
    clearError(input);
    input.classList.add('success');
}

/**
 * Validate single field
 */
function validateField(input, rules) {
    const value = input.value;
    let isValid = true;
    let errorMessage = '';
    
    // Check required
    if (rules.required && !ValidationRules.required(value)) {
        isValid = false;
        errorMessage = ErrorMessages.required;
    }
    
    // Skip other validations if field is empty and not required
    if (!rules.required && value.trim() === '') {
        showSuccess(input);
        return true;
    }
    
    // Check email
    if (isValid && rules.email && !ValidationRules.email(value)) {
        isValid = false;
        errorMessage = ErrorMessages.email;
    }
    
    // Check minLength
    if (isValid && rules.minLength && !ValidationRules.minLength(value, rules.minLength)) {
        isValid = false;
        errorMessage = ErrorMessages.minLength(rules.minLength);
    }
    
    // Check maxLength
    if (isValid && rules.maxLength && !ValidationRules.maxLength(value, rules.maxLength)) {
        isValid = false;
        errorMessage = ErrorMessages.maxLength(rules.maxLength);
    }
    
    // Check password
    if (isValid && rules.password && !ValidationRules.password(value)) {
        isValid = false;
        errorMessage = ErrorMessages.password;
    }
    
    // Check phone
    if (isValid && rules.phone && !ValidationRules.phone(value)) {
        isValid = false;
        errorMessage = ErrorMessages.phone;
    }
    
    // Check alpha
    if (isValid && rules.alpha && !ValidationRules.alpha(value)) {
        isValid = false;
        errorMessage = ErrorMessages.alpha;
    }
    
    // Check alphaNumeric
    if (isValid && rules.alphaNumeric && !ValidationRules.alphaNumeric(value)) {
        isValid = false;
        errorMessage = ErrorMessages.alphaNumeric;
    }
    
    // Check numeric
    if (isValid && rules.numeric && !ValidationRules.numeric(value)) {
        isValid = false;
        errorMessage = ErrorMessages.numeric;
    }
    
    // Check file size
    if (isValid && rules.fileSize && input.files && input.files[0]) {
        if (!ValidationRules.fileSize(input.files[0], rules.fileSize)) {
            isValid = false;
            errorMessage = ErrorMessages.fileSize(rules.fileSize);
        }
    }
    
    // Check file type
    if (isValid && rules.fileType && input.files && input.files[0]) {
        if (!ValidationRules.fileType(input.files[0], rules.fileType)) {
            isValid = false;
            errorMessage = ErrorMessages.fileType(rules.fileType);
        }
    }
    
    // Check password match
    if (isValid && rules.matchWith) {
        const matchInput = document.querySelector(rules.matchWith);
        if (matchInput && !ValidationRules.passwordMatch(value, matchInput.value)) {
            isValid = false;
            errorMessage = ErrorMessages.passwordMatch;
        }
    }
    
    // Display result
    if (isValid) {
        showSuccess(input);
    } else {
        showError(input, errorMessage);
    }
    
    return isValid;
}

/**
 * Form Validator Class
 */
class FormValidator {
    constructor(formId, validationRules) {
        this.form = document.getElementById(formId);
        this.rules = validationRules;
        this.init();
    }
    
    init() {
        if (!this.form) return;
        
        // Disable HTML5 validation
        this.form.setAttribute('novalidate', 'novalidate');
        
        // Add real-time validation on blur
        Object.keys(this.rules).forEach(fieldName => {
            const input = this.form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                input.addEventListener('blur', () => {
                    validateField(input, this.rules[fieldName]);
                });
                
                // Clear error on input
                input.addEventListener('input', () => {
                    if (input.classList.contains('error')) {
                        clearError(input);
                    }
                });
            }
        });
        
        // Validate on submit
        this.form.addEventListener('submit', (e) => {
            if (!this.validateForm()) {
                e.preventDefault();
                e.stopPropagation();
                
                // Focus on first error field
                const firstError = this.form.querySelector('.error');
                if (firstError) {
                    firstError.focus();
                    firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            }
        });
    }
    
    validateForm() {
        let isFormValid = true;
        
        Object.keys(this.rules).forEach(fieldName => {
            const input = this.form.querySelector(`[name="${fieldName}"]`);
            if (input) {
                const isValid = validateField(input, this.rules[fieldName]);
                if (!isValid) {
                    isFormValid = false;
                }
            }
        });
        
        return isFormValid;
    }
}

/**
 * Initialize Login Form Validation
 */
function initLoginValidation() {
    new FormValidator('loginForm', {
        email: {
            required: true,
            email: true
        },
        password: {
            required: true,
            minLength: 6
        }
    });
}

/**
 * Initialize Registration Form Validation
 */
function initRegistrationValidation() {
    new FormValidator('registrationForm', {
        fullname: {
            required: true,
            alpha: true,
            minLength: 3,
            maxLength: 100
        },
        email: {
            required: true,
            email: true
        },
        password: {
            required: true,
            password: true
        },
        confirm_password: {
            required: true,
            matchWith: '[name="password"]'
        },
        contact_no: {
            required: true,
            phone: true
        },
        address: {
            required: true,
            minLength: 10,
            maxLength: 255
        },
        town: {
            required: true,
            minLength: 3
        },
        profile_picture: {
            fileSize: 5, // 5MB max
            fileType: ['jpg', 'jpeg', 'png', 'gif']
        }
    });
}

/**
 * Initialize Order Status Update Form Validation
 */
function initOrderStatusValidation() {
    new FormValidator('orderStatusForm', {
        order_status: {
            required: true
        },
        payment_status: {
            required: true
        }
    });
}

/**
 * Initialize Product Form Validation
 */
function initProductValidation() {
    new FormValidator('productForm', {
        product_name: {
            required: true,
            minLength: 3,
            maxLength: 100
        },
        description: {
            required: true,
            minLength: 10
        },
        price: {
            required: true,
            numeric: true
        },
        stock_quantity: {
            required: true,
            numeric: true
        },
        category: {
            required: true
        },
        main_image: {
            fileSize: 5,
            fileType: ['jpg', 'jpeg', 'png']
        }
    });
}

/**
 * Initialize Review Form Validation
 */
function initReviewValidation() {
    new FormValidator('reviewForm', {
        rating: {
            required: true,
            numeric: true
        },
        review_text: {
            required: true,
            minLength: 10,
            maxLength: 500
        }
    });
}

// Export functions
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        FormValidator,
        validateField,
        ValidationRules,
        initLoginValidation,
        initRegistrationValidation,
        initOrderStatusValidation,
        initProductValidation,
        initReviewValidation
    };
}