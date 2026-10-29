<?php
$pageTitle = 'My Appointments — BarberBook';
require_once '../includes/config.php';
requireLogin();
$db = getDB();
$userId = $_SESSION['user_id'];

$appts = $db->prepare('
    SELECT a.*, b.name as barber_name, b.barber_id as bid, s.name as shop_name, sv.name as service_name, sv.price
    FROM appointments a
    JOIN barbers b ON b.id=a.barber_id
    JOIN shops s ON s.id=b.shop_id
    LEFT JOIN services sv ON sv.id=a.service_id
    WHERE a.user_id=?
    ORDER BY a.appointment_date DESC, a.appointment_time DESC
');
$appts->execute([$userId]);
$allAppts = $appts->fetchAll();

$upcoming = array_filter($allAppts, fn($a) => $a['appointment_date'] >= date('Y-m-d') && in_array($a['status'],['confirmed','pending']));
$past = array_filter($allAppts, fn($a) => !in_array($a, array_values($upcoming)));

require_once '../includes/header.php';
require_once '../includes/navbar.php';
?>
<div class="container" style="padding-top:40px;padding-bottom:60px">
  <h1 class="mb-3">My Appointments</h1>
  
  <!-- Upcoming -->
  <h3 style="margin-bottom:16px">Upcoming</h3>
  <?php if(empty($upcoming)): ?>
    <div class="empty-state" style="padding:40px;border:1px solid var(--border);border-radius:16px">
      <div class="icon">📅</div>
      <p>No upcoming appointments.</p>
      <a href="dashboard.php" class="btn btn-primary" style="margin-top:16px">Book Now</a>
    </div>
  <?php else: ?>
    <?php foreach($upcoming as $a): ?>
    <div class="appt-item">
      <div class="appt-date-box">
        <div class="day"><?=date('j',strtotime($a['appointment_date']))?></div>
        <div class="month"><?=date('M',strtotime($a['appointment_date']))?></div>
      </div>
      <div class="appt-info">
        <div class="appt-name"><?=e($a['barber_name'])?> · <?=e($a['shop_name'])?></div>
        <div class="appt-meta"><?=date('l',strtotime($a['appointment_date']))?> at <?=date('g:i A',strtotime($a['appointment_time']))?></div>
        <?php if($a['service_name']): ?><div class="appt-meta"><?=e($a['service_name'])?> — $<?=number_format($a['price'],2)?></div><?php endif; ?>
        <?php if($a['status']==='reschedule_pending'): ?>
          <div class="appt-meta" style="color:var(--warning)">⚠ Reschedule pending — check your email</div>
        <?php endif; ?>
      </div>
      <div>
        <span class="badge badge-<?=$a['status']==='confirmed'?'success':($a['status']==='cancelled'?'danger':'warning')?>"><?=ucfirst($a['status'])?></span>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="divider"></div>
  <h3 style="margin-bottom:16px">Past Appointments</h3>
  <?php if(empty($past)): ?>
    <p class="text-muted text-small">No past appointments yet.</p>
  <?php else: ?>
    <?php foreach($past as $a): ?>
    <div class="appt-item" style="opacity:0.7">
      <div class="appt-date-box">
        <div class="day"><?=date('j',strtotime($a['appointment_date']))?></div>
        <div class="month"><?=date('M',strtotime($a['appointment_date']))?></div>
      </div>
      <div class="appt-info">
        <div class="appt-name"><?=e($a['barber_name'])?> · <?=e($a['shop_name'])?></div>
        <div class="appt-meta"><?=date('g:i A',strtotime($a['appointment_time']))?> · <?=e($a['service_name'] ?? 'General')?></div>
      </div>
      <span class="badge badge-<?=$a['status']==='completed'?'success':($a['status']==='cancelled'?'danger':'muted')?>"><?=ucfirst($a['status'])?></span>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php require_once '../includes/footer.php'; ?>
