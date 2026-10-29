<?php
$pageTitle = 'Login — BarberBook';
require_once 'includes/config.php';
if (isLoggedIn()) { header('Location: '.SITE_URL.'/client/dashboard.php'); exit; }

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_email']= $user['email'];
        header('Location: '.SITE_URL.'/client/dashboard.php'); exit;
    } else {
        $error = 'Invalid email or password.';
    }
}
require_once 'includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo">
      <h1>✂ BarberBook</h1>
      <p>Welcome back — sign in to your account</p>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?>
    <?php $f = getFlash(); if ($f): ?><div class="alert alert-<?= $f['type'] ?>"><?= e($f['msg']) ?></div><?php endif; ?>
    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required placeholder="••••••••">
      </div>
      <div class="flex-between mt-1 mb-3">
        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
          <input type="checkbox" name="remember"> Remember me
        </label>
        <a href="forgot-password.php" style="font-size:13px">Forgot password?</a>
      </div>
      <button type="submit" class="btn btn-primary btn-full btn-lg">Sign In</button>
    </form>
    <div class="auth-divider">or</div>
    <p class="text-center text-small">Don't have an account? <a href="register.php"><strong>Create one</strong></a></p>
    <div class="divider"></div>
    <p class="text-center text-small text-muted">
      Are you a barber? <a href="barber/login.php">Barber login →</a>
    </p>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
