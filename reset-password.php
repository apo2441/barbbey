<?php
$pageTitle = 'Reset Password — BarberBook';
require_once 'includes/config.php';

$token = $_GET['token'] ?? '';
$db = getDB();
$stmt = $db->prepare('SELECT * FROM users WHERE reset_token = ? AND reset_expires > NOW()');
$stmt->execute([$token]);
$user = $stmt->fetch();

$error = ''; $success = false;
if (!$user) {
    $error = 'This reset link is invalid or has expired.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pass = $_POST['password'] ?? '';
    $conf = $_POST['confirm'] ?? '';
    if (strlen($pass) < 8) { $error = 'Password must be at least 8 characters.'; }
    elseif ($pass !== $conf) { $error = 'Passwords do not match.'; }
    else {
        $hash = password_hash($pass, PASSWORD_BCRYPT);
        $db->prepare('UPDATE users SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?')
           ->execute([$hash, $user['id']]);
        $success = true;
    }
}
require_once 'includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo"><h1>✂ BarberBook</h1><p>Choose a new password</p></div>
    <?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div>
    <?php elseif ($success): ?>
      <div class="alert alert-success">✓ Password updated! You can now <a href="login.php">log in</a>.</div>
    <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label class="form-label">New Password</label>
          <input type="password" name="password" class="form-control" required placeholder="Min 8 characters">
        </div>
        <div class="form-group">
          <label class="form-label">Confirm New Password</label>
          <input type="password" name="confirm" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary btn-full">Update Password</button>
      </form>
    <?php endif; ?>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
