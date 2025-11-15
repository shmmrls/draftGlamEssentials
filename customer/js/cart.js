// cart.js - Enhanced with custom confirmation modals

// Quantity update function
function updateQuantity(button, change, maxStock) {
    const form = button.closest('.quantity-form');
    const input = form.querySelector('input[name="quantity"]');
    let currentValue = parseInt(input.value) || 1;
    let newValue = currentValue + change;
    
    if (newValue < 1) newValue = 1;
    if (newValue > maxStock) newValue = maxStock;
    
    input.value = newValue;
}

// Create modal HTML
function createModal(type, data = {}) {
    const modal = document.createElement('div');
    modal.className = 'modal-overlay';
    modal.id = 'confirmModal';
    
    if (type === 'remove') {
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Remove Item from Cart?</h3>
                </div>
                <div class="modal-body">
                    <div class="modal-product">
                        <img src="${data.image}" alt="${data.name}" class="modal-product-image">
                        <div class="modal-product-info">
                            <div class="modal-product-name">${data.name}</div>
                            <div class="modal-product-details">
                                Quantity: ${data.quantity} | Price: ₱${parseFloat(data.price).toFixed(2)}
                            </div>
                        </div>
                    </div>
                    <p class="modal-message">
                        Are you sure you want to remove this item from your shopping cart? 
                        This action cannot be undone.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-confirm" onclick="confirmRemove()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Remove Item
                    </button>
                </div>
            </div>
        `;
    } else if (type === 'clear') {
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h3 class="modal-title">Clear Shopping Cart?</h3>
                </div>
                <div class="modal-body">
                    <div class="modal-warning">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
                            <line x1="12" y1="9" x2="12" y2="13"/>
                            <line x1="12" y1="17" x2="12.01" y2="17"/>
                        </svg>
                        <span>This will remove all items from your cart</span>
                    </div>
                    <p class="modal-message">
                        You have <strong>${data.itemCount} ${data.itemCount === 1 ? 'item' : 'items'}</strong> 
                        in your cart with a total value of <strong>₱${parseFloat(data.total).toFixed(2)}</strong>.
                        <br><br>
                        Are you sure you want to clear your entire shopping cart? This action cannot be undone.
                    </p>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">
                        Cancel
                    </button>
                    <button type="button" class="btn btn-confirm" onclick="confirmClear()">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                        Clear Cart
                    </button>
                </div>
            </div>
        `;
    }
    
    return modal;
}

// Show modal
function showModal(modal) {
    document.body.appendChild(modal);
    // Trigger reflow for animation
    modal.offsetHeight;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

// Close modal
function closeModal() {
    const modal = document.getElementById('confirmModal');
    if (modal) {
        modal.classList.remove('active');
        setTimeout(() => {
            modal.remove();
            document.body.style.overflow = '';
        }, 300);
    }
}

// Store form reference for confirmation
let pendingForm = null;

// Confirm remove action
function confirmRemove() {
    if (pendingForm) {
        closeModal();
        // Submit the form after modal closes
        setTimeout(() => {
            pendingForm.submit();
        }, 300);
    }
}

// Confirm clear action
function confirmClear() {
    if (pendingForm) {
        closeModal();
        setTimeout(() => {
            pendingForm.submit();
        }, 300);
    }
}

// Initialize modal handlers when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    
    // Handle remove item buttons
    const removeButtons = document.querySelectorAll('.btn-remove');
    removeButtons.forEach(button => {
        const form = button.closest('form');
        if (form && form.querySelector('input[name="type"][value="remove"]')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const cartItem = this.closest('.cart-item');
                const itemData = {
                    name: cartItem.querySelector('.item-name').textContent.trim(),
                    quantity: cartItem.querySelector('input[name="quantity"]').value,
                    price: cartItem.querySelector('.item-price').textContent.replace('₱', '').replace(',', ''),
                    image: cartItem.querySelector('.item-image img').src
                };
                
                pendingForm = this;
                const modal = createModal('remove', itemData);
                showModal(modal);
            });
        }
    });
    
    // Handle clear cart button
    const clearButton = document.querySelector('button[type="submit"]');
    if (clearButton) {
        const form = clearButton.closest('form');
        if (form && form.querySelector('input[name="type"][value="clear"]')) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Get cart statistics
                const totalItems = document.querySelectorAll('.cart-item').length;
                const subtotalElement = document.querySelector('.summary-row:first-child span:last-child');
                const subtotal = subtotalElement ? parseFloat(subtotalElement.textContent.replace('₱', '').replace(',', '')) : 0;
                
                // Get shipping fee
                const shippingElement = document.querySelector('.summary-row:nth-child(2) span:last-child');
                let shippingFee = 0;
                if (shippingElement) {
                    const shippingText = shippingElement.textContent.trim();
                    if (shippingText !== 'FREE') {
                        shippingFee = parseFloat(shippingText.replace('₱', '').replace(',', '')) || 0;
                    }
                }
                
                // Calculate total
                const total = subtotal + shippingFee;
                
                pendingForm = this;
                const modal = createModal('clear', {
                    itemCount: totalItems,
                    total: total
                });
                showModal(modal);
            });
        }
    }
    
    // Close modal on overlay click
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            closeModal();
        }
    });
    
    // Close modal on ESC key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeModal();
        }
    });
});