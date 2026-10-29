<?php
$pageTitle = 'Barber Login — BarberBook';
require_once '../includes/config.php';
if (isBarber()) { header('Location: '.SITE_URL.'/barber/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM barbers WHERE email=? AND is_active=1');
    $stmt->execute([$email]);
    $barber = $stmt->fetch();
    if ($barber && password_verify($pass, $barber['password'])) {
        $_SESSION['barber_id']   = $barber['id'];
        $_SESSION['barber_name'] = $barber['name'];
        $_SESSION['barber_shop'] = $barber['shop_id'];
        header('Location: '.SITE_URL.'/barber/dashboard.php'); exit;
    } else {
        $error = 'Invalid credentials or account inactive.';
    }
}
require_once '../includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo">
      <h1>✂ BarberBook</h1>
      <p>Barber Portal — Sign in</p>
    </div>
    <?php if($error): ?><div class="alert alert-danger"><?=e($error)?></div><?php endif; ?>
    <?php $f=getFlash(); if($f): ?><div class="alert alert-<?=$f['type']?>"><?=e($f['msg'])?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required placeholder="barber@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">Sign In as Barber</button>
    </form>
    <div class="divider"></div>
    <p class="text-center text-small text-muted">
      Client? <a href="<?=SITE_URL?>/login.php">Client login →</a>
    </p>
  </div>
</div>
<?php require_once '../includes/footer.php'; ?>
