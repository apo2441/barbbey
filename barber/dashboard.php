<?php
$pageTitle = 'Barber Dashboard — BarberBook';
require_once '../includes/config.php';
requireBarber();
require_once '../includes/mailer.php';
$db = getDB();
$bid = $_SESSION['barber_id'];

// Load barber
$barber = $db->prepare('SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=?');
$barber->execute([$bid]); $barber = $barber->fetch();

// Stats
$todayAppts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE barber_id=? AND appointment_date=CURDATE() AND status='confirmed'");
$todayAppts->execute([$bid]); $todayCount = $todayAppts->fetchColumn();

$weekAppts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE barber_id=? AND YEARWEEK(appointment_date)=YEARWEEK(NOW()) AND status='confirmed'");
$weekAppts->execute([$bid]); $weekCount = $weekAppts->fetchColumn();

$totalAppts = $db->prepare("SELECT COUNT(*) FROM appointments WHERE barber_id=? AND status='completed'");
$totalAppts->execute([$bid]); $totalCount = $totalAppts->fetchColumn();

// Today's appointments
$todayList = $db->prepare("
    SELECT a.*, u.name as client_name, u.email as client_email, u.phone as client_phone, sv.name as service_name
    FROM appointments a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN services sv ON sv.id=a.service_id
    WHERE a.barber_id=? AND a.appointment_date=CURDATE() AND a.status NOT IN ('cancelled')
    ORDER BY a.appointment_time
");
$todayList->execute([$bid]); $todayAppointments = $todayList->fetchAll();

// Upcoming (next 7 days)
$upcoming = $db->prepare("
    SELECT a.*, u.name as client_name, u.email as client_email, u.phone as client_phone, sv.name as service_name
    FROM appointments a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN services sv ON sv.id=a.service_id
    WHERE a.barber_id=? AND a.appointment_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    AND a.status NOT IN ('cancelled') ORDER BY a.appointment_date, a.appointment_time
");
$upcoming->execute([$bid]); $upcomingAppts = $upcoming->fetchAll();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="<?=SITE_URL?>/barber/dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav">
    <li><span style="font-size:13px;color:var(--muted)"><?=e($barber['name'])?> · <?=e($barber['shop_name'])?></span></li>
    <li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li>
  </ul>
</nav>

<div class="dashboard">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=strtoupper(substr($barber['name'],0,1))?></div>
      <div class="sidebar-name"><?=e($barber['name'])?></div>
      <div class="sidebar-role">Barber · <?=e($barber['shop_name'])?></div>
    </div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php" class="active"><span class="icon">📋</span> Overview</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
      <li><a href="schedule.php"><span class="icon">🕐</span> My Schedule</a></li>
      <li><a href="services.php"><span class="icon">✂</span> Services & Pricing</a></li>
      <li><a href="manual-booking.php"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>

  <!-- Main -->
  <div class="main-content">
    <h1 style="margin-bottom:8px">Good <?=(date('H')<12) ? 'Morning' : ((date('H')<17) ? 'Afternoon' : 'Evening')?>, <?=e(explode(' ',$barber['name'])[0])?> ✂</h1>
    <p class="text-muted" style="margin-bottom:32px"><?=date('l, F j, Y')?></p>

    <!-- Stats -->
    <div class="grid grid-3 mb-3" style="margin-bottom:32px">
      <div class="stat-card">
        <div class="stat-value"><?=$todayCount?></div>
        <div class="stat-label">Today's Appointments</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?=$weekCount?></div>
        <div class="stat-label">This Week</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?=$totalCount?></div>
        <div class="stat-label">Total Completed</div>
      </div>
    </div>

    <!-- Today's Schedule -->
    <h2 style="margin-bottom:16px">Today's Schedule</h2>
    <?php if(empty($todayAppointments)): ?>
      <div class="empty-state" style="border:1px solid var(--border);border-radius:16px;padding:48px">
        <div class="icon">☀️</div><p>No appointments today. Enjoy your day!</p>
      </div>
    <?php else: ?>
      <?php foreach($todayAppointments as $a): ?>
      <div class="appt-item">
        <div class="appt-date-box">
          <div class="day"><?=date('g:i',strtotime($a['appointment_time']))?></div>
          <div class="month"><?=date('A',strtotime($a['appointment_time']))?></div>
        </div>
        <div class="appt-info">
          <div class="appt-name"><?=e($a['client_name'] ?? 'Walk-in')?></div>
          <div class="appt-meta"><?=e($a['client_email'])?> <?=$a['client_phone']?'· '.e($a['client_phone']):''?></div>
          <?php if($a['service_name']): ?><div class="appt-meta">Service: <?=e($a['service_name'])?></div><?php endif; ?>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <span class="badge badge-<?=$a['status']==='confirmed'?'success':'warning'?>"><?=ucfirst($a['status'])?></span>
          <a href="appointments.php?action=cancel&id=<?=$a['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this appointment?')">Cancel</a>
          <a href="appointments.php?reschedule=<?=$a['id']?>" class="btn btn-warning btn-sm">Reschedule</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="divider"></div>
    <h2 style="margin-bottom:16px">Upcoming (Next 7 Days)</h2>
    <?php if(empty($upcomingAppts)): ?>
      <p class="text-muted text-small">No upcoming appointments this week.</p>
    <?php else: ?>
      <?php foreach($upcomingAppts as $a): ?>
      <?php if($a['appointment_date']===date('Y-m-d')) continue; ?>
      <div class="appt-item">
        <div class="appt-date-box">
          <div class="day"><?=date('j',strtotime($a['appointment_date']))?></div>
          <div class="month"><?=date('M',strtotime($a['appointment_date']))?></div>
        </div>
        <div class="appt-info">
          <div class="appt-name"><?=e($a['client_name'] ?? 'Walk-in')?></div>
          <div class="appt-meta"><?=date('g:i A',strtotime($a['appointment_time']))?> · <?=e($a['service_name'] ?? 'General')?></div>
          <div class="appt-meta"><?=e($a['client_email'])?></div>
        </div>
        <div style="display:flex;gap:8px">
          <a href="appointments.php?action=cancel&id=<?=$a['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel?')">Cancel</a>
          <a href="appointments.php?reschedule=<?=$a['id']?>" class="btn btn-warning btn-sm">Reschedule</a>
        </div>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
