<?php
$pageTitle = 'Forgot Password — BarberBook';
require_once 'includes/config.php';
require_once 'includes/mailer.php';

$sent = false; $error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $db = getDB();
    $stmt = $db->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        $db->prepare('UPDATE users SET reset_token=?, reset_expires=? WHERE id=?')
           ->execute([$token, $expires, $user['id']]);
        $link = SITE_URL . '/reset-password.php?token=' . $token;
        $content = '<p>Hi '.e($user['name']).',</p>
        <p>Click the button below to reset your password. This link expires in 1 hour.</p>
        <a href="'.$link.'" class="btn">Reset Password</a>
        <p style="margin-top:20px;font-size:12px;color:#aaa">If you did not request this, ignore this email.</p>';
        sendMail($email, $user['name'], 'Reset Your Password — BarberBook', emailTemplate('Password Reset', $content));
    }
    $sent = true; // Always show success (don't reveal if email exists)
}
require_once 'includes/header.php';
?>
<div class="auth-wrapper">
  <div class="auth-card">
    <div class="auth-logo">
      <h1>✂ BarberBook</h1>
      <p>Reset your password</p>
    </div>
    <?php if ($sent): ?>
      <div class="alert alert-success">
        ✓ If that email is registered, you'll receive a reset link shortly. Check your inbox.
      </div>
      <p class="text-center mt-2"><a href="login.php">← Back to Login</a></p>
    <?php else: ?>
      <form method="POST">
        <div class="form-group">
          <label class="form-label">Your Email Address</label>
          <input type="email" name="email" class="form-control" required placeholder="you@example.com">
        </div>
        <button type="submit" class="btn btn-primary btn-full">Send Reset Link</button>
      </form>
      <p class="text-center mt-2 text-small"><a href="login.php">← Back to Login</a></p>
    <?php endif; ?>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
