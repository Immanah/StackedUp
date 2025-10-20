// === Confirm dialogs ===
document.addEventListener('click', (e) => {
  if (e.target.matches('[data-confirm]')) {
    if (!confirm(e.target.getAttribute('data-confirm'))) e.preventDefault();
  }
});

// === Theme toggle ===
const root = document.documentElement;
if (localStorage.getItem('theme') === 'dark') {
  root.classList.add('dark');
}
document.getElementById('themeToggle')?.addEventListener('click', () => {
  root.classList.toggle('dark');
  localStorage.setItem('theme', root.classList.contains('dark') ? 'dark' : 'light');
});

// === Notifications poll ===
(function(){
  const btn = document.getElementById('notifBtn');
  const count = document.getElementById('notifCount');
  async function poll(){
    try {
      const res = await fetch('notifications_unread_count.php');
      const d = await res.json();
      count.textContent = d.count > 0 ? d.count : '';
    } catch(e){}
  }
  if (btn) btn.addEventListener('click', ()=> location.href='notifications_list.php');
  poll();
  setInterval(poll, 60 * 1000);
})();

// === Sidebar Toggle ===
document.addEventListener('DOMContentLoaded', () => {
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('menuToggle');

  if (toggle && sidebar) {
    toggle.addEventListener('click', (e) => {
      e.stopPropagation();
      sidebar.classList.toggle('active');
    });
  }

  // click outside to close sidebar on mobile
  document.addEventListener('click', (e) => {
    if (!sidebar?.classList.contains('active')) return;
    const withinSidebar = sidebar.contains(e.target);
    const isToggle = e.target.id === 'menuToggle';
    if (!withinSidebar && !isToggle) sidebar.classList.remove('active');
  });
});

