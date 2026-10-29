<?php
$pageTitle = 'Services — BarberBook';
require_once '../includes/config.php';
requireBarber();
$db = getDB();
$bid = $_SESSION['barber_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    if (isset($_POST['delete_service'])) {
        $db->prepare('DELETE FROM services WHERE id=? AND barber_id=?')->execute([(int)$_POST['delete_service'],$bid]);
        setFlash('success','Service removed.');
    } else {
        $name = trim($_POST['name']); $price=(float)$_POST['price']; $dur=(int)$_POST['duration']; $desc=trim($_POST['description']??'');
        if ($name && $price>0) {
            $db->prepare('INSERT INTO services (barber_id,name,description,duration_minutes,price) VALUES (?,?,?,?,?)')->execute([$bid,$name,$desc,$dur,$price]);
            setFlash('success','Service added!');
        } else { setFlash('danger','Name and price are required.'); }
    }
    header('Location: services.php'); exit;
}

$services = $db->prepare('SELECT * FROM services WHERE barber_id=? ORDER BY name');
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
      <li><a href="services.php" class="active"><span class="icon">✂</span> Services & Pricing</a></li>
      <li><a href="manual-booking.php"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <h1 style="margin-bottom:32px">Services & Pricing</h1>

    <div class="grid grid-2" style="gap:32px">
      <div class="card">
        <div class="card-header"><strong>Add Service</strong></div>
        <div class="card-body">
          <form method="POST">
            <div class="form-group"><label class="form-label">Service Name</label>
              <input type="text" name="name" class="form-control" required placeholder="e.g. Fade, Beard Trim…"></div>
            <div class="form-group"><label class="form-label">Description (optional)</label>
              <textarea name="description" class="form-control" rows="2"></textarea></div>
            <div class="grid grid-2" style="gap:12px">
              <div class="form-group"><label class="form-label">Price ($)</label>
                <input type="number" name="price" step="0.01" min="0" class="form-control" required placeholder="25.00"></div>
              <div class="form-group"><label class="form-label">Duration (min)</label>
                <select name="duration" class="form-control">
                  <option value="30">30 min</option><option value="45">45 min</option>
                  <option value="60">60 min</option><option value="90">90 min</option>
                </select></div>
            </div>
            <button type="submit" class="btn btn-primary btn-full">Add Service</button>
          </form>
        </div>
      </div>
      <div>
        <h3 style="margin-bottom:16px">Your Services</h3>
        <?php if(empty($services)): ?>
          <div class="empty-state" style="border:1px solid var(--border);border-radius:16px;padding:40px">
            <div class="icon">✂</div><p>No services added yet.</p>
          </div>
        <?php else: ?>
          <?php foreach($services as $svc): ?>
          <div class="flex-between" style="padding:14px 16px;background:var(--white);border:1px solid var(--border);border-radius:10px;margin-bottom:8px">
            <div>
              <div style="font-weight:600"><?=e($svc['name'])?></div>
              <div class="text-small text-muted"><?=$svc['duration_minutes']?> min · $<?=number_format($svc['price'],2)?></div>
              <?php if($svc['description']): ?><div class="text-small text-muted"><?=e($svc['description'])?></div><?php endif; ?>
            </div>
            <form method="POST">
              <input type="hidden" name="delete_service" value="<?=$svc['id']?>">
              <button class="btn btn-danger btn-sm" onclick="return confirm('Delete this service?')">Remove</button>
            </form>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
