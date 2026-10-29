<?php
$pageTitle = 'My Profile — BarberBook';
require_once '../includes/config.php';
requireBarber();
$db = getDB();
$bid = $_SESSION['barber_id'];

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name']); $phone=trim($_POST['phone']??'');
    $bio   = trim($_POST['bio']??''); $spec=trim($_POST['specialties']??'');
    $newPass = $_POST['new_password']??'';
    if ($name) {
        $db->prepare('UPDATE barbers SET name=?,phone=?,bio=?,specialties=? WHERE id=?')->execute([$name,$phone,$bio,$spec,$bid]);
        $_SESSION['barber_name']=$name;
    }
    if ($newPass && strlen($newPass)>=8) {
        $db->prepare('UPDATE barbers SET password=? WHERE id=?')->execute([password_hash($newPass,PASSWORD_BCRYPT),$bid]);
    }
    setFlash('success','Profile updated!');
    header('Location: profile.php'); exit;
}

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
      <li><a href="manual-booking.php"><span class="icon">➕</span> Add Appointment</a></li>
      <li><a href="profile.php" class="active"><span class="icon">👤</span> My Profile</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <h1 style="margin-bottom:32px">My Profile</h1>
    <div class="card" style="max-width:600px">
      <div class="card-header">
        <strong>Edit Profile</strong>
        <span class="text-small text-muted" style="margin-left:12px">Barber ID: <?=e($barber['barber_id'])?></span>
      </div>
      <div class="card-body">
        <form method="POST">
          <div class="form-group"><label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?=e($barber['name'])?>" required></div>
          <div class="form-group"><label class="form-label">Phone</label>
            <input type="tel" name="phone" class="form-control" value="<?=e($barber['phone'])?>"></div>
          <div class="form-group"><label class="form-label">Specialties</label>
            <input type="text" name="specialties" class="form-control" value="<?=e($barber['specialties'])?>" placeholder="e.g. Fades, Beards, Kids cuts"></div>
          <div class="form-group"><label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="3"><?=e($barber['bio'])?></textarea></div>
          <div class="divider"></div>
          <div class="form-group"><label class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min 8 characters" minlength="8"></div>
          <div class="flex-between">
            <div>
              <div class="text-small text-muted">Shop: <?=e($barber['shop_name'])?></div>
              <div class="text-small text-muted">Email: <?=e($barber['email'])?></div>
            </div>
            <button type="submit" class="btn btn-primary">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
