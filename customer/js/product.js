// Change main product image
function changeImage(imgSrc, element) {
    document.getElementById('mainImage').src = imgSrc;
    
    // Update active thumbnail
    document.querySelectorAll('.thumbnail-item').forEach(item => {
        item.classList.remove('active');
    });
    element.classList.add('active');
}

// Quantity controls
function increaseQty(max) {
    const input = document.getElementById('quantity');
    const currentValue = parseInt(input.value);
    if (currentValue < max) {
        input.value = currentValue + 1;
    }
}

function decreaseQty() {
    const input = document.getElementById('quantity');
    const currentValue = parseInt(input.value);
    if (currentValue > 1) {
        input.value = currentValue - 1;
    }
}

// Review form validation (Quiz 4 - 10pts: No HTML5 validation)
document.addEventListener('DOMContentLoaded', function() {
    const reviewForm = document.getElementById('reviewForm');
    
    if (reviewForm) {
        reviewForm.addEventListener('submit', function(e) {
            let isValid = true;
            
            // Clear previous errors
            document.querySelectorAll('.form-error').forEach(el => {
                el.classList.remove('show');
                el.textContent = '';
            });
            
            // Review text validation
            const reviewText = document.getElementById('review_text').value.trim();
            const reviewError = document.getElementById('review-error');
            
            if (reviewText === '') {
                reviewError.textContent = 'Review text is required';
                reviewError.classList.add('show');
                isValid = false;
            } else if (reviewText.length < 10) {
                reviewError.textContent = 'Review must be at least 10 characters long';
                reviewError.classList.add('show');
                isValid = false;
            } else if (reviewText.length > 1000) {
                reviewError.textContent = 'Review must not exceed 1000 characters';
                reviewError.classList.add('show');
                isValid = false;
            }
            
            // Rating validation (ensure one is selected)
            const ratingSelected = document.querySelector('input[name="rating"]:checked');
            if (!ratingSelected) {
                alert('Please select a rating');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                return false;
            }
            
            // If validation passes, allow form submission
            return true;
        });
    }
    
    // Character counter for review textarea
    const reviewTextarea = document.getElementById('review_text');
    if (reviewTextarea) {
        reviewTextarea.addEventListener('input', function() {
            const charCount = this.value.length;
            const hint = this.nextElementSibling;
            
            if (hint && hint.classList.contains('form-hint')) {
                if (charCount > 0) {
                    hint.textContent = `${charCount} / 1000 characters`;
                } else {
                    hint.textContent = 'Minimum 10 characters';
                }
                
                if (charCount > 1000) {
                    hint.style.color = '#b91c1c';
                } else {
                    hint.style.color = 'rgba(0,0,0,0.4)';
                }
            }
        });
    }
});