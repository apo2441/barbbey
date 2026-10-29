<?php
$pageTitle = 'Admin Dashboard — BarberBook';
require_once '../includes/config.php';
requireAdmin();
$db = getDB();

$totalBarbers = $db->query('SELECT COUNT(*) FROM barbers')->fetchColumn();
$activeBarbers = $db->query("SELECT COUNT(*) FROM barbers WHERE is_active=1")->fetchColumn();
$totalShops = $db->query('SELECT COUNT(*) FROM shops')->fetchColumn();
$todayAppts = $db->query("SELECT COUNT(*) FROM appointments WHERE appointment_date=CURDATE() AND status='confirmed'")->fetchColumn();
$unpaidBarbers = $db->query("SELECT COUNT(*) FROM barbers WHERE subscription_status='unpaid' AND is_active=1")->fetchColumn();

// Recent barbers
$barbers = $db->query("SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id ORDER BY b.created_at DESC LIMIT 10")->fetchAll();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav">
    <li><span style="font-size:13px;color:var(--muted)">Admin: <?=e($_SESSION['admin_name'])?></span></li>
    <li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li>
  </ul>
</nav>

<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar">A</div>
      <div class="sidebar-name"><?=e($_SESSION['admin_name'])?></div>
      <div class="sidebar-role">Administrator</div>
    </div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php" class="active"><span class="icon">📋</span> Overview</a></li>
      <li><a href="barbers.php"><span class="icon">✂</span> Manage Barbers</a></li>
      <li><a href="shops.php"><span class="icon">🏪</span> Manage Shops</a></li>
      <li><a href="subscriptions.php"><span class="icon">💳</span> Subscriptions</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
    </ul>
  </aside>

  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <h1 style="margin-bottom:32px">Admin Overview</h1>

    <div class="grid grid-4" style="margin-bottom:32px">
      <div class="stat-card">
        <div class="stat-value"><?=$totalBarbers?></div>
        <div class="stat-label">Total Barbers</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?=$activeBarbers?></div>
        <div class="stat-label">Active Barbers</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?=$todayAppts?></div>
        <div class="stat-label">Today's Appointments</div>
      </div>
      <div class="stat-card" style="border-top:3px solid var(--warning)">
        <div class="stat-value" style="color:var(--warning)"><?=$unpaidBarbers?></div>
        <div class="stat-label">Unpaid Subscriptions</div>
      </div>
    </div>

    <div class="flex-between mb-3">
      <h2>Recent Barbers</h2>
      <a href="barbers.php" class="btn btn-primary btn-sm">+ Add Barber</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead><tr><th>Name</th><th>Shop</th><th>ID</th><th>Status</th><th>Subscription</th><th>Actions</th></tr></thead>
        <tbody>
          <?php foreach($barbers as $b): ?>
          <tr>
            <td><div style="font-weight:500"><?=e($b['name'])?></div><div style="font-size:12px;color:var(--text-light)"><?=e($b['email'])?></div></td>
            <td><?=e($b['shop_name'])?></td>
            <td><code><?=e($b['barber_id'])?></code></td>
            <td><span class="badge badge-<?=$b['is_active']?'success':'danger'?>"><?=$b['is_active']?'Active':'Inactive'?></span></td>
            <td><span class="badge badge-<?=$b['subscription_status']==='paid'?'success':($b['subscription_status']==='trial'?'warning':'danger')?>"><?=ucfirst($b['subscription_status'])?></span></td>
            <td>
              <div style="display:flex;gap:6px">
                <a href="barbers.php?toggle=<?=$b['id']?>" class="btn btn-sm btn-<?=$b['is_active']?'warning':'success'?>"><?=$b['is_active']?'Deactivate':'Activate'?></a>
                <a href="subscriptions.php?barber=<?=$b['id']?>" class="btn btn-sm btn-outline">Payment</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
