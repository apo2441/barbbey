<?php
$pageTitle = 'Manage Barbers — BarberBook';
require_once '../includes/config.php';
requireAdmin();
$db = getDB();

// Toggle active
if (isset($_GET['toggle'])) {
    $id=(int)$_GET['toggle'];
    $db->prepare('UPDATE barbers SET is_active = NOT is_active WHERE id=?')->execute([$id]);
    setFlash('success','Barber status updated.');
    header('Location: barbers.php'); exit;
}

// Delete
if (isset($_GET['delete'])) {
    $db->prepare('DELETE FROM barbers WHERE id=?')->execute([(int)$_GET['delete']]);
    setFlash('success','Barber deleted.');
    header('Location: barbers.php'); exit;
}

// Create barber
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name  = trim($_POST['name']); $email = trim($_POST['email']);
    $pass  = $_POST['password'];   $shopId= (int)$_POST['shop_id'];
    $phone = trim($_POST['phone']??''); $spec = trim($_POST['specialties']??'');
    $bio   = trim($_POST['bio']??'');
    if ($name && $email && $pass && $shopId) {
        $check=$db->prepare('SELECT id FROM barbers WHERE email=?');
        $check->execute([$email]);
        if ($check->fetch()) { setFlash('danger','Email already in use.'); }
        else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $barberId = generateBarberId();
            $db->prepare('INSERT INTO barbers (barber_id,shop_id,name,email,password,phone,bio,specialties,is_active,subscription_status) VALUES (?,?,?,?,?,?,?,?,1,"trial")')
               ->execute([$barberId,$shopId,$name,$email,$hash,$phone,$bio,$spec]);
            setFlash('success',"Barber created! ID: $barberId");
        }
    } else { setFlash('danger','All required fields must be filled.'); }
    header('Location: barbers.php'); exit;
}

$barbers = $db->query("SELECT b.*, s.name as shop_name FROM barbers b JOIN shops s ON s.id=b.shop_id ORDER BY b.name")->fetchAll();
$shops   = $db->query("SELECT * FROM shops ORDER BY name")->fetchAll();

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
      <li><a href="barbers.php" class="active"><span class="icon">✂</span> Manage Barbers</a></li>
      <li><a href="shops.php"><span class="icon">🏪</span> Manage Shops</a></li>
      <li><a href="subscriptions.php"><span class="icon">💳</span> Subscriptions</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <div class="grid grid-2" style="gap:32px">
      <!-- Create Form -->
      <div>
        <h2 style="margin-bottom:20px">Create Barber Account</h2>
        <div class="card">
          <div class="card-body">
            <form method="POST">
              <div class="form-group"><label class="form-label">Full Name *</label>
                <input type="text" name="name" class="form-control" required placeholder="Barber Name"></div>
              <div class="form-group"><label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Password *</label>
                <input type="password" name="password" class="form-control" required placeholder="Temp password"></div>
              <div class="form-group"><label class="form-label">Assign to Shop *</label>
                <select name="shop_id" class="form-control" required>
                  <option value="">— Select Shop —</option>
                  <?php foreach($shops as $s): ?><option value="<?=$s['id']?>"><?=e($s['name'])?></option><?php endforeach; ?>
                </select></div>
              <div class="form-group"><label class="form-label">Phone</label>
                <input type="tel" name="phone" class="form-control"></div>
              <div class="form-group"><label class="form-label">Specialties</label>
                <input type="text" name="specialties" class="form-control" placeholder="e.g. Fades, Beard Trimming"></div>
              <div class="form-group"><label class="form-label">Bio</label>
                <textarea name="bio" class="form-control" rows="2"></textarea></div>
              <button type="submit" class="btn btn-primary btn-full">Create Barber</button>
            </form>
          </div>
        </div>
      </div>

      <!-- Barbers List -->
      <div>
        <h2 style="margin-bottom:20px">All Barbers (<?=count($barbers)?>)</h2>
        <?php if(empty($barbers)): ?>
          <div class="empty-state" style="border:1px solid var(--border);border-radius:16px;padding:40px">
            <div class="icon">✂</div><p>No barbers yet.</p>
          </div>
        <?php else: ?>
          <?php foreach($barbers as $b): ?>
          <div style="padding:14px 16px;background:var(--white);border:1px solid var(--border);border-radius:10px;margin-bottom:8px">
            <div class="flex-between">
              <div>
                <div style="font-weight:600"><?=e($b['name'])?></div>
                <div class="text-small text-muted"><?=e($b['email'])?> · <?=e($b['shop_name'])?></div>
                <div class="text-small text-muted">ID: <code><?=e($b['barber_id'])?></code></div>
                <div style="margin-top:4px;display:flex;gap:6px">
                  <span class="badge badge-<?=$b['is_active']?'success':'danger'?>"><?=$b['is_active']?'Active':'Inactive'?></span>
                  <span class="badge badge-<?=$b['subscription_status']==='paid'?'success':($b['subscription_status']==='trial'?'warning':'danger')?>"><?=ucfirst($b['subscription_status'])?></span>
                </div>
              </div>
              <div style="display:flex;flex-direction:column;gap:6px">
                <a href="?toggle=<?=$b['id']?>" class="btn btn-sm btn-<?=$b['is_active']?'warning':'success'?>"><?=$b['is_active']?'Deactivate':'Activate'?></a>
                <a href="subscriptions.php?barber=<?=$b['id']?>" class="btn btn-sm btn-outline">Payment</a>
                <a href="?delete=<?=$b['id']?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete barber permanently?')">Delete</a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
