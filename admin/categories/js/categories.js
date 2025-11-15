document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const sortFilter = document.getElementById('sortFilter');
    const resetBtn = document.getElementById('resetFilters');
    const categoriesGrid = document.querySelector('.categories-grid');
    const noResults = document.getElementById('noResults');
    
    let allCards = Array.from(document.querySelectorAll('.category-card'));
    
    function filterAndSort() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const sortBy = sortFilter.value;
        
        // Filter cards
        let visibleCards = allCards.filter(card => {
            const categoryName = card.dataset.name.toLowerCase();
            const categoryId = card.dataset.id;
            
            // Search filter
            const matchesSearch = searchTerm === '' || 
                                 categoryName.includes(searchTerm) || 
                                 categoryId.includes(searchTerm);
            
            return matchesSearch;
        });
        
        // Sort cards
        visibleCards.sort((a, b) => {
            switch(sortBy) {
                case 'latest':
                    return parseInt(b.dataset.id) - parseInt(a.dataset.id);
                case 'oldest':
                    return parseInt(a.dataset.id) - parseInt(b.dataset.id);
                case 'name-asc':
                    return a.dataset.name.localeCompare(b.dataset.name);
                case 'name-desc':
                    return b.dataset.name.localeCompare(a.dataset.name);
                default:
                    return 0;
            }
        });
        
        // Update display
        allCards.forEach(card => card.style.display = 'none');
        
        if (visibleCards.length > 0) {
            visibleCards.forEach(card => {
                card.style.display = '';
                categoriesGrid.appendChild(card); // Re-append in sorted order
            });
            noResults.style.display = 'none';
            categoriesGrid.style.display = 'grid';
        } else {
            noResults.style.display = 'flex';
            categoriesGrid.style.display = 'none';
        }
    }
    
    // Event listeners
    if (searchInput) {
        searchInput.addEventListener('input', filterAndSort);
    }
    
    if (sortFilter) {
        sortFilter.addEventListener('change', filterAndSort);
    }
    
    if (resetBtn) {
        resetBtn.addEventListener('click', function() {
            searchInput.value = '';
            sortFilter.value = 'latest';
            filterAndSort();
        });
    }
    
    // Initial sort
    filterAndSort();
});