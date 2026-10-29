<?php
$pageTitle = 'Appointments — BarberBook';
require_once '../includes/config.php';
requireBarber();
require_once '../includes/mailer.php';
$db = getDB();
$bid = $_SESSION['barber_id'];

// Complete
if (isset($_GET["action"]) && $_GET["action"]==="complete" && isset($_GET["id"])) {
    $id=(int)$_GET["id"];
    $db->prepare("UPDATE appointments SET status='completed' WHERE id=? AND barber_id=?")->execute([$id,$bid]);
    setFlash("success","Appointment marked as completed.");
    header("Location: appointments.php"); exit;
}

// Cancel
if (isset($_GET['action']) && $_GET['action']==='cancel' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $a = $db->prepare('SELECT * FROM appointments WHERE id=? AND barber_id=?');
    $a->execute([$id,$bid]); $appt=$a->fetch();
    if ($appt) {
        $db->prepare("UPDATE appointments SET status='cancelled' WHERE id=?")->execute([$id]);
        sendCancellationEmail($appt['client_email'],$appt['client_name'],$appt['appointment_date'],$appt['appointment_time'],$_SESSION['barber_name']);
        setFlash('success','Appointment cancelled and client notified.');
    }
    header('Location: appointments.php'); exit;
}

// Handle reschedule form submission
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['reschedule_id'])) {
    $id = (int)$_POST['reschedule_id'];
    $newDate = $_POST['new_date'];
    $newTime = $_POST['new_time'];
    $a = $db->prepare('SELECT * FROM appointments WHERE id=? AND barber_id=?');
    $a->execute([$id,$bid]); $appt=$a->fetch();
    if ($appt && $newDate && $newTime) {
        $token = bin2hex(random_bytes(16));
        $db->prepare("UPDATE appointments SET status='reschedule_pending', reschedule_date=?, reschedule_time=?, reschedule_token=? WHERE id=?")
           ->execute([$newDate,$newTime,$token,$id]);
        sendRescheduleRequest($appt['client_email'],$appt['client_name'],$newDate,$newTime,$token,$_SESSION['barber_name']);
        setFlash('success','Reschedule request sent to client.');
    }
    header('Location: appointments.php'); exit;
}

// Load all appointments
$filter = $_GET['filter'] ?? 'upcoming';
$whereClause = match($filter) {
    'today'    => "AND a.appointment_date=CURDATE()",
    'past'     => "AND a.appointment_date < CURDATE()",
    'cancelled'=> "AND a.status='cancelled'",
    default    => "AND a.appointment_date >= CURDATE() AND a.status NOT IN ('cancelled')"
};

$appts = $db->prepare("
    SELECT a.*, u.name as cn, u.email as ce, u.phone as cp, sv.name as svc
    FROM appointments a LEFT JOIN users u ON u.id=a.user_id LEFT JOIN services sv ON sv.id=a.service_id
    WHERE a.barber_id=? $whereClause ORDER BY a.appointment_date, a.appointment_time
");
$appts->execute([$bid]); $appts=$appts->fetchAll();

$rescheduleId = (int)($_GET['reschedule'] ?? 0);
$rAppt = null;
if ($rescheduleId) {
    $rs = $db->prepare('SELECT * FROM appointments WHERE id=? AND barber_id=?');
    $rs->execute([$rescheduleId,$bid]); $rAppt=$rs->fetch();
}

$barber = $db->prepare('SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=?');
$barber->execute([$bid]); $barber=$barber->fetch();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav">
    <li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li>
  </ul>
</nav>

<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=strtoupper(substr($barber['name'],0,1))?></div>
      <div class="sidebar-name"><?=e($barber['name'])?></div>
      <div class="sidebar-role">Barber · <?=e($barber['shop_name'])?></div>
    </div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><span class="icon">📋</span> Overview</a></li>
      <li><a href="appointments.php" class="active"><span class="icon">📅</span> All Appointments</a></li>
      <li><a href="schedule.php"><span class="icon">🕐</span> My Schedule</a></li>
      <li><a href="services.php"><span class="icon">✂</span> Services & Pricing</a></li>
      <li><a href="manual-booking.php"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>

  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    
    <div class="flex-between mb-3">
      <h1>All Appointments</h1>
      <a href="manual-booking.php" class="btn btn-primary">+ Add Appointment</a>
    </div>

    <!-- Filters -->
    <div class="flex-center gap-2 mb-3" style="flex-wrap:wrap">
      <?php foreach(['upcoming'=>'Upcoming','today'=>'Today','past'=>'Past','cancelled'=>'Cancelled'] as $k=>$v): ?>
      <a href="?filter=<?=$k?>" class="btn <?=$filter===$k?'btn-primary':'btn-outline'?> btn-sm"><?=$v?></a>
      <?php endforeach; ?>
    </div>

    <?php if(empty($appts)): ?>
      <div class="empty-state" style="border:1px solid var(--border);border-radius:16px;padding:60px">
        <div class="icon">📅</div><p>No appointments found.</p>
      </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Date & Time</th>
            <th>Client</th>
            <th>Service</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach($appts as $a): ?>
          <tr>
            <td>
              <div style="font-weight:600"><?=date('M j, Y',strtotime($a['appointment_date']))?></div>
              <div style="font-size:12px;color:var(--text-light)"><?=date('g:i A',strtotime($a['appointment_time']))?></div>
            </td>
            <td>
              <div style="font-weight:500"><?=e($a['cn'] ?? 'Walk-in')?></div>
              <div style="font-size:12px;color:var(--text-light)"><?=e($a['ce'])?></div>
              <?php if($a['cp']): ?><div style="font-size:12px;color:var(--text-light)"><?=e($a['cp'])?></div><?php endif; ?>
            </td>
            <td><?=e($a['svc'] ?? '—')?></td>
            <td><span class="badge badge-<?=$a['status']==='confirmed'?'success':($a['status']==='cancelled'?'danger':($a['status']==='completed'?'success':'warning'))?>"><?=ucfirst(str_replace('_',' ',$a['status']))?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap">
                <?php if(!in_array($a['status'],['cancelled','completed'])): ?>
                  <a href="?action=cancel&id=<?=$a['id']?>&filter=<?=$filter?>" class="btn btn-danger btn-sm" onclick="return confirm('Cancel this appointment?')">Cancel</a>
                  <a href="?reschedule=<?=$a['id']?>&filter=<?=$filter?>" class="btn btn-warning btn-sm">Reschedule</a>
                  <a href="?action=complete&id=<?=$a['id']?>&filter=<?=$filter?>" class="btn btn-success btn-sm" onclick="return confirm('Mark as completed?')">✓ Done</a>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Reschedule Modal -->
<?php if($rAppt): ?>
<div class="modal-overlay open">
  <div class="modal">
    <div class="modal-header">
      <h3>Reschedule Appointment</h3>
      <a href="appointments.php" class="modal-close">✕</a>
    </div>
    <p class="text-small text-muted" style="margin-bottom:20px">
      Current: <?=date('M j, Y',strtotime($rAppt['appointment_date']))?> at <?=date('g:i A',strtotime($rAppt['appointment_time']))?><br>
      Client: <strong><?=e($rAppt['client_name'])?></strong>
    </p>
    <p class="alert alert-info">The client will receive an email to accept or decline the new time.</p>
    <form method="POST">
      <input type="hidden" name="reschedule_id" value="<?=$rAppt['id']?>">
      <div class="form-group">
        <label class="form-label">New Date</label>
        <input type="date" name="new_date" class="form-control" required min="<?=date('Y-m-d')?>">
      </div>
      <div class="form-group">
        <label class="form-label">New Time</label>
        <input type="time" name="new_time" class="form-control" required step="1800">
      </div>
      <div class="flex-between">
        <a href="appointments.php" class="btn btn-outline">Cancel</a>
        <button type="submit" class="btn btn-warning">Send Reschedule Request</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php require_once '../includes/footer.php'; ?>
<?php /* Note: 'complete' action needs to be added to appointments.php GET handler */ ?>
