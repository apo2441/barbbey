<?php
$pageTitle = 'Add Appointment — BarberBook';
require_once '../includes/config.php';
requireBarber();
$db = getDB();
$bid = $_SESSION['barber_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $date = $_POST['date']; $time = $_POST['time'];
    $cn = trim($_POST['client_name']); $ce = trim($_POST['client_email']);
    $cp = trim($_POST['client_phone']??''); $svcId = $_POST['service_id']?:null;
    $notes = trim($_POST['notes']??'');
    if ($date && $time && $cn && $ce) {
        $endTime = date('H:i:s', strtotime($time)+1800);
        $db->prepare('INSERT INTO appointments (barber_id,service_id,appointment_date,appointment_time,end_time,client_name,client_email,client_phone,notes,status) VALUES (?,?,?,?,?,?,?,?,?,"confirmed")')
           ->execute([$bid,$svcId,$date,$time,$endTime,$cn,$ce,$cp,$notes]);
        setFlash('success','Appointment added!');
        header('Location: appointments.php'); exit;
    } else { setFlash('danger','Please fill all required fields.'); }
}

$services = $db->prepare('SELECT * FROM services WHERE barber_id=? AND is_active=1 ORDER BY name');
$services->execute([$bid]); $services=$services->fetchAll();
$barber = $db->prepare('SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id WHERE b.id=?');
$barber->execute([$bid]); $barber=$barber->fetch();

require_once '../includes/header.php';
?>
<nav class="navbar">
  <a href="dashboard.php" class="navbar-brand">✂ <span>Barber</span>Book</a>
  <ul class="navbar-nav"><li><a href="logout.php" class="btn btn-outline btn-sm">Logout</a></li></ul>
</nav>
<div class="dashboard">
  <aside class="sidebar">
    <div class="sidebar-user">
      <div class="sidebar-avatar"><?=strtoupper(substr($barber['name'],0,1))?></div>
      <div class="sidebar-name"><?=e($barber['name'])?></div>
      <div class="sidebar-role">Barber</div>
    </div>
    <ul class="sidebar-nav">
      <li><a href="dashboard.php"><span class="icon">📋</span> Overview</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
      <li><a href="schedule.php"><span class="icon">🕐</span> My Schedule</a></li>
      <li><a href="services.php"><span class="icon">✂</span> Services & Pricing</a></li>
      <li><a href="manual-booking.php" class="active"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <h1 style="margin-bottom:32px">Add Manual Appointment</h1>
    <div class="card" style="max-width:600px">
      <div class="card-body">
        <form method="POST">
          <div class="grid grid-2" style="gap:16px">
            <div class="form-group"><label class="form-label">Client Name *</label>
              <input type="text" name="client_name" class="form-control" required placeholder="John Smith"></div>
            <div class="form-group"><label class="form-label">Client Email *</label>
              <input type="email" name="client_email" class="form-control" required placeholder="client@email.com"></div>
            <div class="form-group"><label class="form-label">Client Phone</label>
              <input type="tel" name="client_phone" class="form-control" placeholder="+1 555 000 0000"></div>
            <div class="form-group"><label class="form-label">Service</label>
              <select name="service_id" class="form-control">
                <option value="">— No specific service —</option>
                <?php foreach($services as $s): ?>
                  <option value="<?=$s['id']?>"><?=e($s['name'])?> — $<?=number_format($s['price'],2)?></option>
                <?php endforeach; ?>
              </select></div>
            <div class="form-group"><label class="form-label">Date *</label>
              <input type="date" name="date" class="form-control" required min="<?=date('Y-m-d')?>"></div>
            <div class="form-group"><label class="form-label">Time *</label>
              <input type="time" name="time" class="form-control" required step="1800"></div>
          </div>
          <div class="form-group"><label class="form-label">Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Any notes…"></textarea></div>
          <div class="flex-between">
            <a href="appointments.php" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">Add Appointment</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
