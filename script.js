// Real-time date display
function updateDate() {
    const now = new Date();
    const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
    document.getElementById('current-date').textContent = now.toLocaleDateString('en-US', options);
}

// Initialize date and update every minute
updateDate();
setInterval(updateDate, 60000);

// Modal functionality
window.onclick = function(event) {
    const modals = document.getElementsByClassName('modal');
    for (let modal of modals) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
}

// Search functionality
function setupSearch(inputId, tableId) {
    const searchInput = document.getElementById(inputId);
    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const searchTerm = e.target.value.toLowerCase();
            const rows = document.querySelectorAll(`#${tableId} tbody tr`);
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    }
}

// Initialize all search inputs
document.addEventListener('DOMContentLoaded', function() {
    setupSearch('product-search', 'products-table');
    
    // Add animation to stat cards
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
});

// Confirm before delete
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this item?');
}

// Stock update functionality
function updateStock(productId, action) {
    const quantity = prompt(`Enter quantity to ${action}:`);
    if (quantity && !isNaN(quantity) && quantity > 0) {
        fetch('update_stock.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `product_id=${productId}&quantity=${quantity}&action=${action}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Stock updated successfully!');
                location.reload();
            } else {
                alert('Error updating stock: ' + data.error);
            }
        });
    }
}

// Real-time stock level indicators
function updateStockIndicators() {
    document.querySelectorAll('.stock-level').forEach(indicator => {
        const level = parseInt(indicator.dataset.level);
        const max = parseInt(indicator.dataset.max);
        const percentage = (level / max) * 100;
        
        if (percentage < 20) {
            indicator.style.backgroundColor = '#ef4444';
        } else if (percentage < 50) {
            indicator.style.backgroundColor = '#f59e0b';
        } else {
            indicator.style.backgroundColor = '#10b981';
        }
        
        indicator.style.width = percentage + '%';
    });
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    updateStockIndicators();
    
    // Add fade-in animation to table rows
    const tableRows = document.querySelectorAll('table tbody tr');
    tableRows.forEach((row, index) => {
        row.style.animationDelay = `${index * 0.05}s`;
        row.classList.add('fade-in');
    });
});