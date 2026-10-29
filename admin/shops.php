<?php
$pageTitle = 'Manage Shops — BarberBook';
require_once '../includes/config.php';
requireAdmin();
$db = getDB();

if (isset($_GET['delete'])) {
    $db->prepare('DELETE FROM shops WHERE id=?')->execute([(int)$_GET['delete']]);
    setFlash('success','Shop deleted.'); header('Location: shops.php'); exit;
}

if ($_SERVER['REQUEST_METHOD']==='POST') {
    $name=$_POST['name']; $addr=$_POST['address']; $city=$_POST['city'];
    $phone=$_POST['phone']??''; $email=$_POST['email']??''; $desc=$_POST['description']??'';
    $lat=(float)$_POST['latitude']; $lon=(float)$_POST['longitude'];
    if ($name&&$addr&&$city&&$lat&&$lon) {
        $db->prepare('INSERT INTO shops (name,address,city,phone,email,description,latitude,longitude) VALUES (?,?,?,?,?,?,?,?)')->execute([$name,$addr,$city,$phone,$email,$desc,$lat,$lon]);
        setFlash('success','Shop added!');
    } else { setFlash('danger','Required fields missing.'); }
    header('Location: shops.php'); exit;
}

$shops = $db->query("SELECT s.*, COUNT(b.id) as barber_count FROM shops s LEFT JOIN barbers b ON b.shop_id=s.id GROUP BY s.id ORDER BY s.name")->fetchAll();
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
      <li><a href="shops.php" class="active"><span class="icon">🏪</span> Manage Shops</a></li>
      <li><a href="subscriptions.php"><span class="icon">💳</span> Subscriptions</a></li>
      <li><a href="appointments.php"><span class="icon">📅</span> All Appointments</a></li>
    </ul>
  </aside>
  <div class="main-content">
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <div class="grid grid-2" style="gap:32px">
      <div>
        <h2 style="margin-bottom:20px">Add New Shop</h2>
        <div class="card">
          <div class="card-body">
            <form method="POST">
              <div class="form-group"><label class="form-label">Shop Name *</label><input type="text" name="name" class="form-control" required></div>
              <div class="form-group"><label class="form-label">Address *</label><input type="text" name="address" class="form-control" required></div>
              <div class="form-group"><label class="form-label">City *</label><input type="text" name="city" class="form-control" required></div>
              <div class="grid grid-2" style="gap:12px">
                <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control"></div>
                <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control"></div>
                <div class="form-group"><label class="form-label">Latitude *</label><input type="number" name="latitude" step="any" class="form-control" required placeholder="41.0082"></div>
                <div class="form-group"><label class="form-label">Longitude *</label><input type="number" name="longitude" step="any" class="form-control" required placeholder="28.9784"></div>
              </div>
              <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
              <button type="submit" class="btn btn-primary btn-full">Add Shop</button>
            </form>
          </div>
        </div>
      </div>
      <div>
        <h2 style="margin-bottom:20px">All Shops (<?=count($shops)?>)</h2>
        <?php foreach($shops as $s): ?>
        <div style="padding:14px 16px;background:var(--white);border:1px solid var(--border);border-radius:10px;margin-bottom:8px">
          <div class="flex-between">
            <div>
              <div style="font-weight:600"><?=e($s['name'])?></div>
              <div class="text-small text-muted"><?=e($s['address'])?>, <?=e($s['city'])?></div>
              <div class="text-small text-muted"><?=$s['barber_count']?> barbers · <?=e($s['latitude'])?>, <?=e($s['longitude'])?></div>
            </div>
            <a href="?delete=<?=$s['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete shop and all its barbers?')">Delete</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
