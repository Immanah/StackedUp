// orders.js
document.addEventListener('DOMContentLoaded', () => {
    const tabs = Array.from(document.querySelectorAll('.tab'));
    const ordersWrap = document.getElementById('ordersWrap');
    const backBtn = document.getElementById('backBtn');

    // Back button logic
    backBtn.addEventListener('click', () => {
        if (history.length > 1) history.back();
        else window.location.href = 'index.php';
    });

    // Tabs filtering
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Set aria-selected for accessibility
            tabs.forEach(t => t.setAttribute('aria-selected', 'false'));
            tab.setAttribute('aria-selected', 'true');

            const filter = tab.dataset.filter; // all | pending | complete

            const orders = Array.from(ordersWrap.querySelectorAll('.order-card'));
            let hasVisible = false;

            orders.forEach(order => {
                const status = order.dataset.status.toLowerCase();
                if (filter === 'all' || status === filter) {
                    order.style.display = '';
                    hasVisible = true;
                } else {
                    order.style.display = 'none';
                }
            });

            // Optional: show empty state if no orders match
            const emptyState = ordersWrap.querySelector('.empty');
            if (emptyState) emptyState.style.display = hasVisible ? 'none' : '';
        });

        // Keyboard accessibility
        tab.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                tab.click();
            }
        });
    });
});
