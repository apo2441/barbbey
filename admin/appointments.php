<?php
$pageTitle = 'All Appointments — BarberBook';
require_once '../includes/config.php';
requireAdmin();
$db = getDB();

$appts = $db->query("
    SELECT a.*, b.name as barber_name, s.name as shop_name, sv.name as service_name
    FROM appointments a JOIN barbers b ON b.id=a.barber_id JOIN shops s ON s.id=b.shop_id
    LEFT JOIN services sv ON sv.id=a.service_id
    ORDER BY a.appointment_date DESC, a.appointment_time DESC LIMIT 200
")->fetchAll();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav"><li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li></ul>
</nav>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user"><div class="sidebar-avatar">A</div><div class="sidebar-name"><?=e($_SESSION['admin_name'])?></div><div class="sidebar-role">Administrator</div></div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><span class="icon">📋</span> Overview</a></li>
      <li><a href="barbers.php"><span class="icon">✂</span> Manage Barbers</a></li>
      <li><a href="shops.php"><span class="icon">🏪</span> Manage Shops</a></li>
      <li><a href="subscriptions.php"><span class="icon">💳</span> Subscriptions</a></li>
      <li><a href="appointments.php" class="active"><span class="icon">📅</span> All Appointments</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <h1 style="margin-bottom:32px">All Appointments</h1>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Time</th><th>Client</th><th>Barber</th><th>Shop</th><th>Service</th><th>Status</th></tr></thead>
        <tbody>
          <?php foreach($appts as $a): ?>
          <tr>
            <td><?=date('M j, Y',strtotime($a['appointment_date']))?></td>
            <td><?=date('g:i A',strtotime($a['appointment_time']))?></td>
            <td>
              <div style="font-weight:500"><?=e($a['client_name'])?></div>
              <div class="text-small text-muted"><?=e($a['client_email'])?></div>
            </td>
            <td><?=e($a['barber_name'])?></td>
            <td><?=e($a['shop_name'])?></td>
            <td><?=e($a['service_name']??'—')?></td>
            <td><span class="badge badge-<?=$a['status']==='confirmed'?'success':($a['status']==='cancelled'?'danger':($a['status']==='completed'?'success':'warning'))?>"><?=ucfirst(str_replace('_',' ',$a['status']))?></span></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
