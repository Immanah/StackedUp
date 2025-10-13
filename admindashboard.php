<?php
require_once __DIR__ . '/admin_auth.php';
require_once __DIR__ . '/header.php';

// gather summary stats
$counts = [];
$queries = [
  'products' => "SELECT COUNT(*) AS cnt FROM products",
  'orders' => "SELECT COUNT(*) AS cnt FROM orders",
  'users' => "SELECT COUNT(*) AS cnt FROM users",
  'bookings' => "SELECT COUNT(*) AS cnt FROM bookings",
  'messages' => "SELECT COUNT(*) AS cnt FROM messages",
];
foreach ($queries as $k => $sql) {
    $r = $mysqli->query($sql);
    $row = $r->fetch_assoc();
    $counts[$k] = $row['cnt'] ?? 0;
}

// recent messages
$msgs = $mysqli->query("SELECT message_id,name,email,LEFT(message,120) AS preview,created_at FROM messages ORDER BY created_at DESC LIMIT 6");
?>
<div class="card">
  <h3>Welcome back, <?= e($ADMIN['name']) ?></h3>
  <p>Role: <?= e($ADMIN['role']) ?></p>
</div>
<div class="card" id="dashboardWidgets">
  <h4>Quick Overview</h4>
  <div id="widgetsGrid" style="display:flex;gap:12px;flex-wrap:wrap"></div>
  <canvas id="revenueSparkline" width="600" height="80" style="margin-top:12px"></canvas>
</div>
<div class="card" style="display:flex;gap:12px;">
  <div style="flex:1">
    <h4>Products</h4>
    <div class="card"><?= e($counts['products']) ?></div>
  </div>
  <div style="flex:1">
    <h4>Orders</h4>
    <div class="card"><?= e($counts['orders']) ?></div>
  </div>
  <div style="flex:1">
    <h4>Users</h4>
    <div class="card"><?= e($counts['users']) ?></div>
  </div>
  <div style="flex:1">
    <h4>Bookings</h4>
    <div class="card"><?= e($counts['bookings']) ?></div>
  </div>
  <div style="flex:1">
    <h4>Messages</h4>
    <div class="card"><?= e($counts['messages']) ?></div>
  </div>
</div>
<div class="card">
  <h4>Monthly Revenue (last 12 months)</h4>
  <canvas id="revenueChart" width="800" height="320"></canvas>
</div>
<div class="card">
  <h4>Recent Messages</h4>
  <table class="table">
    <thead>
      <tr><th>Name</th><th>Email</th><th>Preview</th><th>When</th></tr>
    </thead>
    <tbody>
      <?php while ($m = $msgs->fetch_assoc()): ?>
        <tr>
          <td><?= e($m['name']) ?></td>
          <td><?= e($m['email']) ?></td>
          <td><?= e($m['preview']) ?></td>
          <td><?= e($m['created_at']) ?></td>
        </tr>
      <?php endwhile; ?>
    </tbody>
  </table>
</div>
<?php require_once __DIR__ . '/footer.php'; ?>

<script>
  fetch('dashboard_stats.php').then(r=>r.json()).then(d=>{
    if (!d.ok) return;
    const months = d.months.map(m=>m.month);
    const revenue = d.months.map(m=>m.revenue);
    // simple canvas chart
    const canvas = document.getElementById('revenueChart');
    const ctx = canvas.getContext('2d');
    const max = Math.max(...revenue, 10);
    ctx.clearRect(0,0,canvas.width,canvas.height);
    // draw axes & bars
    const padding = 40;
    const w = canvas.width - padding*2;
    const h = canvas.height - padding*2;
    const barW = w / revenue.length * 0.7;
    revenue.forEach((val,i)=>{
      const x = padding + i*(w/revenue.length) + (w/revenue.length - barW)/2;
      const barH = (val / max) * h;
      ctx.fillRect(x, padding + (h - barH), barW, barH);
      ctx.fillText(months[i].slice(5), x, padding + h + 12);
    });
  });
  fetch('dashboard_widgets.php').then(r=>r.json()).then(data=>{
  if(!data.ok) return;
  const grid = document.getElementById('widgetsGrid');
  grid.innerHTML = `
    <div class="card" style="padding:12px"><strong>Customers</strong><div style="font-size:20px">${data.user_count}</div></div>
    <div class="card" style="padding:12px"><strong>Orders (this month)</strong><div style="font-size:20px">${data.orders_this_month}</div></div>
    <div class="card" style="padding:12px"><strong>Revenue (this month)</strong><div style="font-size:20px">R${Number(data.revenue_this_month).toFixed(2)}</div></div>
    <div class="card" style="padding:12px"><strong>Unread Notifications</strong><div style="font-size:20px">${data.unread_notifications}</div></div>
  `;
  // top categories list
  const catsHtml = data.top_categories.map(c=>`<div style="padding:6px">${c.name} — R${Number(c.sales).toFixed(2)}</div>`).join('');
  grid.insertAdjacentHTML('beforeend', `<div class="card" style="min-width:240px"><strong>Top Categories</strong>${catsHtml}</div>`);

  // sparkline: reuse months from dashboard_stats endpoint
  fetch('dashboard_stats.php').then(r=>r.json()).then(s=>{
    if(!s.ok) return;
    const ctx = document.getElementById('revenueSparkline').getContext('2d');
    const rev = s.months.map(m=>m.revenue);
    const max = Math.max(...rev,1);
    ctx.clearRect(0,0,600,80);
    ctx.beginPath();
    rev.forEach((v,i)=>{
      const x = 10 + i*(580/(rev.length-1));
      const y = 70 - (v/max)*50;
      if (i===0) ctx.moveTo(x,y); else ctx.lineTo(x,y);
    });
    ctx.stroke();
    // fill
    ctx.lineTo(590,70); ctx.lineTo(10,70); ctx.closePath(); ctx.globalAlpha=0.06; ctx.fillStyle='black'; ctx.fill(); ctx.globalAlpha=1;
  });
});
  </script>

