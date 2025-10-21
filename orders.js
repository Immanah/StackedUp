// orders.js
document.addEventListener('DOMContentLoaded', () => {
    const tabs = Array.from(document.querySelectorAll('.order-tab'));
    const ordersContainer = document.getElementById('orders-container');
    const emptyState = document.getElementById('empty-state');
    const userWelcome = document.getElementById('userWelcome');
    
    // Toast notification function
    function showToast(type, title, message) {
        const toast = document.getElementById('toast');
        const toastTitle = document.getElementById('toastTitle');
        const toastText = document.getElementById('toastText');
        
        toast.className = `toast ${type}`;
        toastTitle.textContent = title;
        toastText.textContent = message;
        
        toast.classList.add('show');
        
        // Auto-hide after 5 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 5000);
    }
    
    // Modal functions
    function openModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    }
    
    function closeModal(modalId) {
        const modal = document.getElementById(modalId);
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
    
    // Close modals when clicking outside
    document.querySelectorAll('.modal').forEach(modal => {
        modal.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal(modal.id);
            }
        });
    });
    
    // Modal event listeners
    document.getElementById('closeDetailsModal').addEventListener('click', () => closeModal('orderDetailsModal'));
    document.getElementById('closeDetailsBtn').addEventListener('click', () => closeModal('orderDetailsModal'));
    document.getElementById('closeExtendModal').addEventListener('click', () => closeModal('extendRentalModal'));
    document.getElementById('cancelExtendBtn').addEventListener('click', () => closeModal('extendRentalModal'));
    
    // Load user data and orders
    function loadUserData() {
        fetch('orders.php')
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Error loading user data:', data.error);
                    // Use demo data for testing
                    loadDemoData();
                    return;
                }
                
                // Set user greeting
                userWelcome.textContent = `Hi, ${data.user.name}`;
                
                // Display orders
                if (data.orders && data.orders.length > 0) {
                    displayOrders(data.orders);
                } else {
                    // If no real orders, show demo orders for testing
                    loadDemoData();
                }
            })
            .catch(error => {
                console.error('Error loading orders:', error);
                // Use demo data if API fails
                loadDemoData();
            });
    }
    
    // Demo data for testing
    function loadDemoData() {
        const demoOrders = [
            {
                id: 1,
                orderNumber: 'OZ-2023-4892',
                total: 650,
                status: 'active',
                createdAt: '2023-11-15',
                rentalStart: '2023-11-18',
                rentalEnd: '2023-11-22',
                items: [
                    {
                        productId: 101,
                        title: 'Elegant Navy Evening Gown',
                        quantity: 1,
                        price: 650,
                        image: null
                    }
                ]
            },
            {
                id: 2,
                orderNumber: 'OZ-2023-5123',
                total: 850,
                status: 'upcoming',
                createdAt: '2023-11-10',
                rentalStart: '2023-11-25',
                rentalEnd: '2023-11-28',
                items: [
                    {
                        productId: 102,
                        title: 'Sparkling Silver Matric Dance Dress',
                        quantity: 1,
                        price: 850,
                        image: null
                    }
                ]
            },
            {
                id: 3,
                orderNumber: 'OZ-2023-4671',
                total: 450,
                status: 'completed',
                createdAt: '2023-10-28',
                rentalStart: '2023-10-31',
                rentalEnd: '2023-11-02',
                items: [
                    {
                        productId: 103,
                        title: 'Classic Black Cocktail Dress',
                        quantity: 1,
                        price: 450,
                        image: null
                    }
                ]
            }
        ];
        
        userWelcome.textContent = 'Hi, Demo User';
        displayOrders(demoOrders);
    }
    
    // Display orders in the grid
    function displayOrders(orders) {
        ordersContainer.innerHTML = '';
        
        if (orders.length === 0) {
            emptyState.style.display = 'block';
            ordersContainer.style.display = 'none';
            return;
        }
        
        emptyState.style.display = 'none';
        ordersContainer.style.display = 'grid';
        
        orders.forEach(order => {
            const orderCard = createOrderCard(order);
            ordersContainer.appendChild(orderCard);
        });
        
        // Apply initial filter
        filterOrders('all');
    }
    
    // Create order card HTML
    function createOrderCard(order) {
        const card = document.createElement('div');
        card.className = 'order-card';
        card.setAttribute('data-status', order.status);
        
        const statusClass = `status-${order.status}`;
        const statusText = getStatusText(order.status);
        
        const itemsHtml = order.items.map(item => `
            <div class="order-item">
                <div class="item-image">
                    ${item.image ? `<img src="${item.image}" alt="${item.title}">` : '[Image]'}
                </div>
                <div class="item-details">
                    <div class="item-name">${item.title}</div>
                    <div class="item-meta">Quantity: ${item.quantity}</div>
                    ${order.rentalStart ? `<div class="item-meta">Rental: ${formatDate(order.rentalStart)} - ${formatDate(order.rentalEnd)}</div>` : ''}
                </div>
                <div class="item-price">R${item.price.toFixed(2)}</div>
            </div>
        `).join('');
        
        card.innerHTML = `
            <div class="order-header">
                <div>
                    <div class="order-id">#${order.orderNumber}</div>
                    <div class="order-date">Placed on ${formatDate(order.createdAt)}</div>
                </div>
                <div class="order-status ${statusClass}">${statusText}</div>
            </div>
            
            <div class="order-items">
                ${itemsHtml}
            </div>
            
            <div class="order-total">
                <div>Total</div>
                <div>R${order.total.toFixed(2)}</div>
            </div>
            
            <div class="order-actions">
                <button class="btn view-details-btn" data-order-id="${order.id}">View Details</button>
                ${order.status === 'active' ? `<button class="btn btn-primary extend-rental-btn" data-order-id="${order.id}">Extend Rental</button>` : ''}
                ${order.status === 'completed' ? `<button class="btn btn-primary rent-again-btn" data-order-id="${order.id}">Rent Again</button>` : ''}
            </div>
        `;
        
        // Add event listeners to buttons
        card.querySelector('.view-details-btn').addEventListener('click', () => showOrderDetails(order));
        if (order.status === 'active') {
            card.querySelector('.extend-rental-btn').addEventListener('click', () => showExtendRentalModal(order));
        }
        if (order.status === 'completed') {
            card.querySelector('.rent-again-btn').addEventListener('click', () => rentAgain(order));
        }
        
        return card;
    }
    
    // Show order details in modal
    function showOrderDetails(order) {
        const modalContent = document.getElementById('orderDetailsContent');
        
        const itemsHtml = order.items.map(item => `
            <div class="order-item">
                <div class="item-image">
                    ${item.image ? `<img src="${item.image}" alt="${item.title}">` : '[Image]'}
                </div>
                <div class="item-details">
                    <div class="item-name">${item.title}</div>
                    <div class="item-meta">Quantity: ${item.quantity} | Price: R${item.price.toFixed(2)}</div>
                    <div class="item-meta">Subtotal: R${(item.quantity * item.price).toFixed(2)}</div>
                </div>
            </div>
        `).join('');
        
        modalContent.innerHTML = `
            <div style="margin-bottom: 20px;">
                <strong>Order Number:</strong> ${order.orderNumber}<br>
                <strong>Order Date:</strong> ${formatDate(order.createdAt)}<br>
                <strong>Status:</strong> ${getStatusText(order.status)}<br>
                ${order.rentalStart ? `<strong>Rental Period:</strong> ${formatDate(order.rentalStart)} - ${formatDate(order.rentalEnd)}<br>` : ''}
                <strong>Total Amount:</strong> R${order.total.toFixed(2)}
            </div>
            
            <h4 style="margin-bottom: 15px;">Items</h4>
            <div class="order-items">
                ${itemsHtml}
            </div>
        `;
        
        openModal('orderDetailsModal');
    }
    
    // Show extend rental modal
    function showExtendRentalModal(order) {
        const currentStartDate = document.getElementById('newStartDate');
        const currentEndDate = document.getElementById('newEndDate');
        const currentPeriod = document.getElementById('currentRentalPeriod');
        
        // Set current dates
        const startDate = new Date(order.rentalStart);
        const endDate = new Date(order.rentalEnd);
        
        currentPeriod.value = `${formatDate(order.rentalStart)} - ${formatDate(order.rentalEnd)}`;
        
        // Set minimum dates for extension
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        currentStartDate.min = formatDateForInput(tomorrow);
        currentEndDate.min = formatDateForInput(tomorrow);
        
        // Set default extension (7 days from current end date)
        const defaultExtension = new Date(endDate);
        defaultExtension.setDate(defaultExtension.getDate() + 7);
        
        currentStartDate.value = formatDateForInput(endDate);
        currentEndDate.value = formatDateForInput(defaultExtension);
        
        // Calculate extension cost
        calculateExtensionCost();
        
        // Add event listeners for date changes
        currentStartDate.addEventListener('change', calculateExtensionCost);
        currentEndDate.addEventListener('change', calculateExtensionCost);
        
        // Confirm extension
        document.getElementById('confirmExtendBtn').onclick = () => {
            const newStart = currentStartDate.value;
            const newEnd = currentEndDate.value;
            const reason = document.getElementById('extensionReason').value;
            
            if (!newStart || !newEnd) {
                showToast('error', 'Error', 'Please select both start and end dates');
                return;
            }
            
            if (new Date(newStart) >= new Date(newEnd)) {
                showToast('error', 'Error', 'End date must be after start date');
                return;
            }
            
            // In a real implementation, this would send a request to your backend
            showToast('success', 'Extension Requested', 'Your rental extension request has been submitted for approval');
            closeModal('extendRentalModal');
        };
        
        openModal('extendRentalModal');
    }
    
    // Calculate extension cost
    function calculateExtensionCost() {
        const startDate = new Date(document.getElementById('newStartDate').value);
        const endDate = new Date(document.getElementById('newEndDate').value);
        
        if (!startDate || !endDate || isNaN(startDate) || isNaN(endDate)) {
            document.getElementById('extensionCost').textContent = 'R0';
            return;
        }
        
        const days = Math.ceil((endDate - startDate) / (1000 * 60 * 60 * 24));
        const cost = days * 150; // R150 per day extension rate
        
        document.getElementById('extensionCost').textContent = `R${cost}`;
    }
    
    // Rent again functionality
    function rentAgain(order) {
        // In a real implementation, this would add items to cart
        showToast('success', 'Added to Cart', 'Items from this order have been added to your cart');
        
        // Simulate navigation to catalog after a delay
        setTimeout(() => {
            window.location.href = 'catalog.php';
        }, 2000);
    }
    
    // Helper functions
    function getStatusText(status) {
        const statusMap = {
            'pending': 'Pending',
            'active': 'Active Rental',
            'upcoming': 'Upcoming',
            'completed': 'Completed'
        };
        return statusMap[status] || status;
    }
    
    function formatDate(dateString) {
        const date = new Date(dateString);
        return date.toLocaleDateString('en-ZA', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        });
    }
    
    function formatDateForInput(date) {
        return date.toISOString().split('T')[0];
    }
    
    // Tab filtering functionality
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs
            tabs.forEach(t => t.classList.remove('active'));
            
            // Add active class to clicked tab
            tab.classList.add('active');
            
            // Filter orders
            const filter = tab.getAttribute('data-filter');
            filterOrders(filter);
        });
        
        // Keyboard accessibility
        tab.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                tab.click();
            }
        });
    });
    
    function filterOrders(filter) {
        const orders = document.querySelectorAll('.order-card');
        let visibleCount = 0;
        
        orders.forEach(order => {
            if (filter === 'all' || order.getAttribute('data-status') === filter) {
                order.style.display = 'block';
                visibleCount++;
            } else {
                order.style.display = 'none';
            }
        });
        
        // Show empty state if no orders match the filter
        if (visibleCount === 0) {
            ordersContainer.style.display = 'none';
            emptyState.style.display = 'block';
        } else {
            ordersContainer.style.display = 'grid';
            emptyState.style.display = 'none';
        }
    }
    
    // Initialize the page
    loadUserData();
});